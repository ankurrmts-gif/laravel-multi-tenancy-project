<?php
namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleFieldOption;
use App\Models\ModulePermission;
use App\Models\Role;
use App\Models\User,App\Models\Tenant,App\Models\CentralTenantTelations;
use App\Services\ModuleFileStructureService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ModulesController extends Controller
{
    public function getParentMenu()
    {
        // Select modules with parent menu assigned
        $parents = Module::whereNotNull('parent_menu')->select('id', 'menu_title as parent_menu')->get();
        return response()->json(['success' => true, 'data' => $parents]);
    }

    public function getAdmins()
    {
        $admins = User::select('id', 'name')->get();
        return response()->json(['success' => true, 'data' => $admins]);
    }

    public function getModels()
    {
        // List of available models, hardcoded or from config
        $models = [
            ['name' => 'User', 'class' => 'App\\Models\\User', 'table' => 'users'],
            // Add more as needed
        ];
        return response()->json(['success' => true, 'data' => $models]);
    }

    public function getModelFields($modelName)
    {
        // This would dynamically get fields from the model
        // For now, placeholder
        $fields = [
            ['id' => 1, 'column_type_id' => 1, 'column_type' => 'text', 'db_column' => 'name', 'label' => 'Name', 'order_number' => 1],
            // etc.
        ];
        return response()->json(['success' => true, 'data' => $fields]);
    }

    public function index(Request $request)
    {
        if($request->type == 'menu'){
            $user = $request->user();
            tenancy()->end();
            
            if($user->user_type == 'tenant'){
                $modules = Module::with(['permissions'])->where('status', true)->where('tenant_id', $user->tenant_id)->orderBy('order_number')->get();
            }else{
                $modules = Module::with(['permissions'])->where('status', true)->orderBy('order_number')->get();
            }

            $allowed = $modules;

          
            $allowed = $modules->filter(fn($module) => $this->userCanAccessModule($module, $user));
            $tree  = [];
            $items = [];

            foreach ($allowed as $module) {
                $permissions = [];
                if($user->user_type != 'tenant'){
                    if($module->created_by == $user->id){
                        $permissions = [1,2,3,4,5];
                    }else{
                        $permissions = $module->permissions;
                    }
                }else{
                    tenancy()->initialize($user->tenant_id);
                        $module_permission = $user->roles()
                        ->whereHas('permissions', function ($q) use ($module) {
                            $q->where('name', 'like', $module->slug . '_%');
                        })
                        ->with(['permissions' => function ($q) use ($module) {
                            $q->where('name', 'like', $module->slug . '_%');
                        }])
                        ->get()
                        ->pluck('permissions')
                        ->flatten()
                        ->pluck('name')
                        ->values();
                        foreach($module_permission as $perm){
                            if(str_contains($perm, '_access')){
                                $permissions[] = 1;
                            }elseif(str_contains($perm, '_create')){
                                $permissions[] = 2;
                            }elseif(str_contains($perm, '_edit')){
                                $permissions[] = 3;
                            }elseif(str_contains($perm, '_show')){
                                $permissions[] = 4;
                            }elseif(str_contains($perm, '_delete')){
                                $permissions[] = 5;
                            }

                        }
                    tenancy()->end();
                }
              
                $items[$module->id] = [
                    'id'          => $module->id,
                    'menu_title'  => $module->menu_title,
                    'slug'        => $module->slug,
                    'icon'        => $module->icon,
                    'order_number' => $module->order_number,
                    'parent_menu' => $module->parent_menu,
                    'permissions' => $permissions,
                    'children'    => [],
                ];
            }

            foreach ($items as $id => &$item) {
                if ($item['parent_menu'] && isset($items[$item['parent_menu']])) {
                    $items[$item['parent_menu']]['children'][] = &$item;
                } else {
                    $tree[] = &$item;
                }
            }
            unset($item);

            return response()->json(['success' => true, 'data' => $tree]);
        } else {
            $limit = $request->limit ?? 10;
            $sort  = $request->sort ?? 'created_at';
            $dir   = $request->dir ?? 'desc';

            $query = Module::query();

            // 🔍 Search (slug, menu_title, model name)
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('menu_title', 'LIKE', "%{$search}%")
                    ->orWhere('main_model_name', 'LIKE', "%{$search}%");
                });
            }

            // 🔽 Filter (optional)
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $query->orderBy($sort, $dir);

            $modules = $query->with([
                'fields.columnType',
                'assignedAdmins',
                'assignedAgencies'
            ])->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $modules
            ]);
        } 
    }

    private function userCanAccessModule(Module $module, $user)
    {
        if (! $user) {
            return false;
        }

        $permissionName = $module->slug . '_access';

        if($user->user_type === 'tenant'){
            tenancy()->initialize($user->tenant_id);
            if (! $user->hasPermissionTo($permissionName, 'sanctum')) {
                return false;
            }
            tenancy()->end();
        }

        if($module->created_by == $user->id){
            return true;
        }

        // user_type restrictions
        if (! empty($module->user_type) && $module->user_type !== 'all' && $module->user_type !== $user->user_type) {
            return false;
        }


        $permissionCount = ModulePermission::where('module_id', $module->id)->where('permission_name', $permissionName)->count();

        // fallback: if no permission is defined for this module, allow it
        if ($permissionCount == 0) {
            if (! $user->hasPermissionTo($permissionName, 'sanctum')) {
                return false;
            }
        }

        return true;
    }

    private function resolveTenantId(Request $request, array $moduleData)
    {
        // Respect explicit tenant_id from payload when provided.
        if (! empty($moduleData['tenant_id'])) {
            return $moduleData['tenant_id'];
        }

        // Current authenticated user tenant (most common for tenant-scoped actions)
        if (auth()->check() && auth()->user()->tenant_id) {
            return auth()->user()->tenant_id;
        }

        // Stancl tenancy global helper, if active
        if (function_exists('tenant') && tenant()?->id) {
            return tenant()->id;
        }

        // Fallback request-level tenant id (e.g. central context may pass this value)
        if ($request->filled('tenant_id')) {
            return $request->input('tenant_id');
        }

        return null;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module.main_model_name'        => 'required|string',
            'module.slug'                  => 'required|string|unique:modules,slug',
            'module.menu_title'            => 'required|string',
            'module.parent_menu'           => 'nullable|integer',
            'module.status'                => 'boolean',
            'module.icon'                  => 'nullable|string',
            'module.user_type'             => 'required_without:module.tenant_id|string',
            'module.order_number'          => 'required|integer|unique:modules,order_number',
            'module.tenant_id'             => 'nullable|string',
            'module.actions'               => 'nullable|array',
            'module.assigned_admins'       => 'nullable',
            'module.assigned_agencies'     => 'nullable',
            'module.permissions'           => 'array',
            'fields'                       => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $moduleData = $request->input('module');
        $user = auth()->user();
        // $user = User::find(1);

        //echo "<pre>"; print_r($user); die();

        // Resolve tenant
        $resolvedTenantId = $this->resolveTenantId($request, $moduleData);
        if ($resolvedTenantId !== null) {
            $moduleData['tenant_id'] = $resolvedTenantId;
        }

        $moduleData['created_by'] = $user->id;

        // Create module
        $module = Module::create($moduleData);

        // =============================
        // Assign Admins
        // =============================
        if (!empty($moduleData['assigned_admins']) && is_array($moduleData['assigned_admins'])) {

            $admins = collect($moduleData['assigned_admins'])
                ->map(fn($a) => is_array($a) ? $a['id'] : $a)
                ->toArray();

            $module->assignedAdmins()->attach($admins);
        }

        // =============================
        // Assign Agencies
        // =============================
        if (!empty($moduleData['assigned_agencies']) && is_array($moduleData['assigned_agencies'])) {

            $agencies = collect($moduleData['assigned_agencies'])
                ->map(fn($a) => is_array($a) ? $a['id'] : $a)
                ->toArray();

            $module->assignedAgencies()->attach($agencies);
        }
        // =============================
        // PREPARE PERMISSIONS
        // =============================

        $allPermissionActions = [
            'access',
            'create',
            'edit',
            'show',
            'delete'
        ];

        $permissionActions = [
            1 => 'access',
            2 => 'create',
            3 => 'edit',
            4 => 'show',
            5 => 'delete',
        ];

        $selectedPermissions = [];

        if (!empty($moduleData['permissions'])) {

            foreach ($moduleData['permissions'] as $permId) {

                $action = $permissionActions[$permId] ?? null;

                if ($action) {
                    $selectedPermissions[] = $module->slug . '_' . $action;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE PERMISSIONS
        |--------------------------------------------------------------------------
        */

        $allPermissions = collect($allPermissionActions)->map(function ($action) use ($module) {

            return Permission::firstOrCreate([
                'name' => $module->slug . '_' . $action,
                'guard_name' => 'sanctum'
            ]);

        });


        /*
        |--------------------------------------------------------------------------
        | SUPER ADMIN → ALWAYS ACCESS
        |--------------------------------------------------------------------------
        */

        $superAdminRole = Role::where('name', 'Super Admin')->first();

        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($allPermissions);
        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN USER → ALWAYS ACCESS
        |--------------------------------------------------------------------------
        */

        // foreach ($allPermissions as $permission) {

        //     ModulePermission::updateOrCreate([
        //         'module_id' => $module->id,
        //         'user_id' => $user->id,
        //         'permission_name' => $permission->name
        //     ]);
        // }


        /*
        |--------------------------------------------------------------------------
        | TENANT PERMISSIONS ONLY
        |--------------------------------------------------------------------------
        */

        if (!empty($module->tenant_id) && $user->user_type === 'agency') {
            $tenant = Tenant::find($user->tenant_id);
             tenancy()->initialize($tenant);
                foreach ($allPermissions as $permission) {
                    Permission::firstOrCreate([
                        'name' => $permission->name,
                        'guard_name' => 'sanctum'
                    ]);
                }

                if (!empty($moduleData['tenant_user_type'])) {

                    if ($moduleData['tenant_user_type'] === 'all') {
                        $roles = Role::all();
                    }else{
                        $roles = Role::where('id', $moduleData['tenant_user_type'])->get();
                    }
                    foreach ($roles as $role) {
                        if ($role) {
                            $role->givePermissionTo($selectedPermissions);
                        }
                    }
                }

             tenancy()->end();
        }


        /*
        |--------------------------------------------------------------------------
        | USER TYPE PERMISSIONS
        |--------------------------------------------------------------------------
        */

        if (!empty($moduleData['user_type'])) {

            if ($moduleData['user_type'] === 'admin') {

                if (!empty($moduleData['assigned_admins']) && is_array($moduleData['assigned_admins'])) {

                    foreach ($moduleData['assigned_admins'] as $admin) {

                        $adminId = is_array($admin) ? $admin['id'] : $admin;

                        foreach ($selectedPermissions as $permission) {

                            ModulePermission::updateOrCreate([
                                'module_id' => $module->id,
                                'user_id' => $adminId,
                                'permission_name' => $permission->name
                            ]);
                        }
                    }
                }

                elseif (($moduleData['assigned_admins'] ?? '') === 'all') {

                    $adminTypeUser = User::where('user_type', 'admin')->pluck('id')->toArray();

                    foreach ($adminTypeUser as $adminId) {
                        $user = User::find($adminId);
                        $roles = $user->roles;

                        foreach ($roles as $role) {
                            if ($role) {
                                $role->givePermissionTo($selectedPermissions);
                            }
                        }
                    }
                }
            }


            elseif ($moduleData['user_type'] === 'agency') {

                if (!empty($moduleData['assigned_agencies']) && is_array($moduleData['assigned_agencies'])) {

                    foreach ($moduleData['assigned_agencies'] as $agency) {

                        $agencyId = is_array($agency) ? $agency['id'] : $agency;

                        foreach ($selectedPermissions as $permission) {

                            ModulePermission::updateOrCreate([
                                'module_id' => $module->id,
                                'user_id' => $agencyId,
                                'permission_name' => $permission->name
                            ]);
                        }
                    }
                }

                elseif (($moduleData['assigned_agencies'] ?? '') === 'all') {

                    $agencyTypeUser = User::where('user_type', 'agency')->pluck('id')->toArray();

                    foreach ($agencyTypeUser as $agencyId) {
                        $user = User::find($agencyId);
                        $roles = $user->roles;

                        foreach ($roles as $role) {
                            if ($role) {
                                $role->givePermissionTo($selectedPermissions);
                            }
                        }
                    }
                    
                }
            }


            elseif ($moduleData['user_type'] === 'all') {

                // ✅ Get users with roles in one query
                $users = User::whereIn('user_type', ['agency', 'admin'])
                    ->with('roles')
                    ->get();

                foreach ($users as $user) {
                    foreach ($user->roles as $role) {

                        if ($role) {
                            $role->givePermissionTo($selectedPermissions);
                        }
                    }
                }
            }
        }

        // =============================
        // Fields
        // =============================
        if ($request->has('fields')) {

            foreach ($request->input('fields') as $fieldData) {

                $field = ModuleField::create(array_merge(
                    $fieldData,
                    ['module_id' => $module->id]
                ));

                if (!empty($fieldData['options'])) {

                    foreach ($fieldData['options'] as $option) {

                        ModuleFieldOption::create(array_merge(
                            $option,
                            ['module_field_id' => $field->id]
                        ));
                    }
                }
            }
        }

        // Load relations
        $module->load('fields.columnType');

        // Generate files
        $this->generateModuleFiles($module);

        // $fileService = new ModuleFileStructureService();
        // $fileService->createModuleDirectories($module->slug);
        // $fileService->createGitkeepFiles($module->slug);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully'
        ]);
    }

    public function show($id)
    {
        $module = Module::with(['fields.options', 'assignedAdmins', 'assignedAgencies'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $module]);
    }

    public function update(Request $request, $id)
    {
        return $this->updateWithFields($request, $id);
    }

    public function destroy($id)
    {
        return $this->destroyWithFields($id);
    }

    public function showWithFields($id)
    {
        $module = Module::with(['fields.options', 'assignedAdmins', 'assignedAgencies', 'permissions'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $module]);
    }

    public function old_updateWithFields(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'module.id'         => 'required|integer',
            'module.main_model_name' => 'required|string',
            'module.slug'       => 'required|string|unique:modules,slug,' . $id,
            // similar to store
            'fields'            => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $moduleData       = $request->input('module');
            $user = auth()->user();

            $resolvedTenantId = $this->resolveTenantId($request, $moduleData);
            if ($resolvedTenantId !== null) {
                $moduleData['tenant_id'] = $resolvedTenantId;
            }

            if ($module->created_by == '') {
                $moduleData['created_by'] = $user->id; // preserve original creator if not provided
            }

            $module->update($moduleData);

            // Update assignments
            $module->assignedAdmins()->sync(collect($moduleData['assigned_admins'] ?? [])->pluck('id')->toArray());
            $module->assignedAgencies()->sync(collect($moduleData['assigned_agencies'] ?? [])->pluck('id')->toArray());

            // Update permissions - simplified
            $module->permissions()->delete();
            $allPermissions = [];
            if (isset($moduleData['permissions'])) {
                $permissionActions = [
                    1 => 'access',
                    2 => 'create',
                    3 => 'edit',
                    4 => 'show',
                    5 => 'delete',
                ];

                foreach ($moduleData['permissions'] as $permId) {
                    $action         = $permissionActions[$permId] ?? 'permission_' . $permId;
                    $permissionName = $module->slug . '_' . $action;
                    ModulePermission::create([
                        'module_id'       => $module->id,
                        'user_id'         => $user->id,
                        'permission_name' => $permissionName,
                    ]);
                    $allPermissions[] = $permissionName;
                }
            }

            // Assign permissions based on user_type
            if (! empty($allPermissions) && ! empty($moduleData['user_type'])) {
                $rolesToAssign = [];
                if (in_array($moduleData['user_type'], ['all', 'admin'])) {
                    $rolesToAssign[] = 'admin';
                }
                if (in_array($moduleData['user_type'], ['all', 'customer'])) {
                    $rolesToAssign[] = 'agency';
                }

                foreach ($rolesToAssign as $roleName) {
                    $role = Role::where('name', $roleName)->first();
                    if ($role) {
                        $finalPermissions = [];

                        foreach ($allPermissions as $permissionName) {
                            // ✅ Check or Create permission in Spatie table
                            $permission = Permission::firstOrCreate([
                                'name' => $permissionName,
                                'guard_name' => 'sanctum' // default guard
                            ]);

                            $finalPermissions[] = $permission->name;
                        }

                        $role->givePermissionTo($finalPermissions);
                    }
                }
            }

            // Update fields - simplified, assume replace all
            $module->fields()->delete(); // This will cascade options
            if ($request->has('fields')) {
                foreach ($request->input('fields') as $fieldData) {
                    $field = ModuleField::create(array_merge($fieldData, ['module_id' => $module->id]));
                    if (isset($fieldData['options'])) {
                        foreach ($fieldData['options'] as $option) {
                            ModuleFieldOption::create(array_merge($option, ['module_field_id' => $field->id]));
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Module updated successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateWithFields(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'module.id'                => 'required|integer',
            'module.main_model_name'   => 'required|string',
            'module.slug'              => 'required|string|unique:modules,slug,' . $id,
            'module.menu_title'        => 'required|string',
            'module.parent_menu'       => 'nullable|integer',
            'module.status'            => 'boolean',
            'module.icon'              => 'nullable|string',
            'module.user_type'         => 'nullable|string',
            'module.order_number'      => 'nullable|integer',
            'module.tenant_id'         => 'nullable|string',
            'module.actions'           => 'nullable|array',
            'module.assigned_admins'   => 'nullable',
            'module.assigned_agencies' => 'nullable',
            'module.permissions'       => 'nullable|array',
            'fields'                   => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moduleData = $request->input('module');
        $user       = auth()->user();

        // ── Resolve Tenant ──────────────────────────────────────────────────────
        $resolvedTenantId = $this->resolveTenantId($request, $moduleData);
        if ($resolvedTenantId !== null) {
            $moduleData['tenant_id'] = $resolvedTenantId;
        }

        if (empty($module->created_by)) {
            $moduleData['created_by'] = $user->id;
        }

        // ── Capture OLD fields BEFORE any changes ───────────────────────────────
        $oldFields = $module->fields()->with('columnType')->get()->keyBy('db_column');

        // ── PHASE 1: DB Transaction (Module data, permissions, fields) ──────────
        try {
            DB::beginTransaction();

            // 1. Update Module Record
            $module->update($moduleData);

            // 2. Sync Assignments
            $module->assignedAdmins()->sync(
                collect($moduleData['assigned_admins'] ?? [])
                    ->map(fn($a) => is_array($a) ? $a['id'] : $a)
                    ->toArray()
            );
            $module->assignedAgencies()->sync(
                collect($moduleData['assigned_agencies'] ?? [])
                    ->map(fn($a) => is_array($a) ? $a['id'] : $a)
                    ->toArray()
            );

            // 3. Permissions
            $permissionActions    = [1 => 'access', 2 => 'create', 3 => 'edit', 4 => 'show', 5 => 'delete'];
            $allPermissionActions = ['access', 'create', 'edit', 'show', 'delete'];
            $selectedPermissions  = [];

            $allPermissions = collect($allPermissionActions)->map(function ($action) use ($module) {
                return Permission::firstOrCreate([
                    'name'       => $module->slug . '_' . $action,
                    'guard_name' => 'sanctum',
                ]);
            });

            $superAdminRole = Role::where('name', 'Super Admin')->first();
            if ($superAdminRole) {
                $superAdminRole->givePermissionTo($allPermissions);
            }

            if (!empty($moduleData['permissions'])) {
                foreach ($moduleData['permissions'] as $permId) {
                    $action = $permissionActions[$permId] ?? null;
                    if ($action) {
                        $selectedPermissions[] = $module->slug . '_' . $action;
                    }
                }
            }

            $module->permissions()->delete();

            foreach ($allPermissions as $permission) {
                ModulePermission::updateOrCreate([
                    'module_id'       => $module->id,
                    'user_id'         => $user->id,
                    'permission_name' => $permission->name,
                ]);
            }

            if (!empty($module->tenant_id) && $user->user_type === 'agency') {

                $tenant = Tenant::find($user->tenant_id);

                if ($tenant) {

                    tenancy()->initialize($tenant);

                    // ✅ Ensure permissions exist
                    foreach ($allPermissions as $permission) {
                        Permission::firstOrCreate([
                            'name' => $permission->name,
                            'guard_name' => 'sanctum'
                        ]);
                    }

                    // ✅ Assign to roles
                    if (!empty($moduleData['tenant_user_type'])) {

                        if ($moduleData['tenant_user_type'] === 'all') {
                            $roles = Role::all();
                        } else {
                            $roles = Role::where('id', $moduleData['tenant_user_type'])->get();
                        }

                        foreach ($roles as $role) {
                            if ($role) {
                                // 🔥 KEY CHANGE HERE
                                $existing = $role->permissions->pluck('name');

                                $finalPermissions = $existing
                                    ->reject(fn($perm) => str_starts_with($perm, $module->slug . '_'))
                                    ->merge($selectedPermissions)
                                    ->unique()
                                    ->values()
                                    ->toArray();

                                $role->syncPermissions($finalPermissions);

                            }
                        }
                    }

                    tenancy()->end();
                }
            }

            if (!empty($moduleData['user_type']) && !empty($selectedPermissions)) {
                $userType = $moduleData['user_type'];

                if (in_array($userType, ['admin', 'all'])) {
                    if (!empty($moduleData['assigned_admins']) && is_array($moduleData['assigned_admins'])) {
                        foreach ($moduleData['assigned_admins'] as $admin) {
                            $adminId = is_array($admin) ? $admin['id'] : $admin;
                            foreach ($selectedPermissions as $permName) {
                                $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'sanctum']);
                                ModulePermission::updateOrCreate([
                                    'module_id'       => $module->id,
                                    'user_id'         => $adminId,
                                    'permission_name' => $perm->name,
                                ]);
                            }
                        }
                    } elseif (($moduleData['assigned_admins'] ?? '') === 'all') {
                        $role = Role::where('name', 'admin')->first();
                        if ($role) $role->givePermissionTo($selectedPermissions);
                    }
                }

                if (in_array($userType, ['agency', 'all'])) {
                    if (!empty($moduleData['assigned_agencies']) && is_array($moduleData['assigned_agencies'])) {
                        foreach ($moduleData['assigned_agencies'] as $agency) {
                            $agencyId = is_array($agency) ? $agency['id'] : $agency;
                            foreach ($selectedPermissions as $permName) {
                                $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'sanctum']);
                                ModulePermission::updateOrCreate([
                                    'module_id'       => $module->id,
                                    'user_id'         => $agencyId,
                                    'permission_name' => $perm->name,
                                ]);
                            }
                        }
                    } elseif (($moduleData['assigned_agencies'] ?? '') === 'all') {
                        $role = Role::where('name', 'agency')->first();
                        if ($role) $role->givePermissionTo($selectedPermissions);
                    }
                }
            }

            // Tenant-level permissions
            if (!empty($module->tenant_id) && $user->user_type === 'agency') {
                $tenant = Tenant::find($user->tenant_id);
                tenancy()->initialize($tenant);
                foreach ($allPermissions as $permission) {
                    Permission::firstOrCreate([
                        'name'       => $permission->name,
                        'guard_name' => 'sanctum',
                    ]);
                }
                tenancy()->end();
            }

            // 4. Sync Fields
            $module->fields()->delete();

            if ($request->has('fields')) {
                foreach ($request->input('fields') as $fieldData) {
                    // Remove id so new record create thay
                    unset($fieldData['id']);

                    $field = ModuleField::create(array_merge(
                        $fieldData,
                        ['module_id' => $module->id]
                    ));

                    if (!empty($fieldData['options'])) {
                        foreach ($fieldData['options'] as $option) {
                            unset($option['id']);
                            ModuleFieldOption::create(array_merge(
                                $option,
                                ['module_field_id' => $field->id]
                            ));
                        }
                    }
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'DB Error: ' . $e->getMessage()
            ], 500);
        }

        // ── PHASE 2: File Generation (Transaction BAHAR — migration conflict avoid) ──
        try {
            $module->load('fields.columnType');
            $this->updateModuleFiles($module, $oldFields);
        } catch (\Exception $e) {
            // File generation fail thay to pan module update successful che
            return response()->json([
                'success' => true,
                'message' => 'Module updated successfully (file generation warning: ' . $e->getMessage() . ')'
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Module updated successfully']);
    }

    private function updateModuleFiles($module, $oldFields = null)
    {
        $modelName = $module->main_model_name;
        $table     = strtolower(Str::plural($module->slug));
        $fk        = strtolower(Str::singular($module->slug));
        $baseTime  = now();
        $i         = 1;

        $newFields     = $module->fields->keyBy('db_column');
        $oldColumnKeys = $oldFields ? $oldFields->keys()->toArray() : [];
        $newColumnKeys = $newFields->keys()->toArray();

        $addedColumns   = array_diff($newColumnKeys, $oldColumnKeys);
        $removedColumns = array_diff($oldColumnKeys, $newColumnKeys);

        // ── A. ALTER Migration — ADD new columns + DROP removed columns ──────────
        $upStatements   = '';
        $downStatements = '';

        // ADD
        foreach ($addedColumns as $col) {
            $field     = $newFields[$col];
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);
            $type      = $field->columnType->db_type ?? 'string';

            if ($field->model_name && !$field->is_multiple) {
                $upStatements   .= "\n        \$table->unsignedBigInteger('{$col}')->nullable();";
                $downStatements .= "\n        \$table->dropColumn('{$col}');";
            } elseif (!in_array($inputType, [14, 15])) {
                $upStatements   .= "\n        \$table->{$type}('{$col}')->nullable();";
                $downStatements .= "\n        \$table->dropColumn('{$col}');";
            } elseif (in_array($inputType, [14, 15]) && !$field->is_multiple) {
                $upStatements   .= "\n        \$table->string('{$col}')->nullable();";
                $downStatements .= "\n        \$table->dropColumn('{$col}');";
            }
            // Multiple file → alag table (niche handle)
        }

        // DROP
        foreach ($removedColumns as $col) {
            $oldField  = $oldFields[$col];
            $inputType = $oldField->column_type_id ?? ($oldField->columnType->column_type_id ?? null);

            // Multiple file → alag table drop (niche handle)
            if (in_array($inputType, [14, 15]) && $oldField->is_multiple) {
                continue;
            }

            // Column exist kare to j drop karo
            $upStatements .= "\n        if (\\Schema::hasColumn('{$table}', '{$col}')) {";
            $upStatements .= "\n            \$table->dropColumn('{$col}');";
            $upStatements .= "\n        }";

            // Rollback best-effort
            $type = $oldField->columnType->db_type ?? 'string';
            if ($oldField->model_name && !$oldField->is_multiple) {
                $downStatements .= "\n        \$table->unsignedBigInteger('{$col}')->nullable();";
            } elseif (!in_array($inputType, [14, 15])) {
                $downStatements .= "\n        \$table->{$type}('{$col}')->nullable();";
            } else {
                $downStatements .= "\n        \$table->string('{$col}')->nullable();";
            }
        }

        if (!empty(trim($upStatements))) {
            $alterDate     = $baseTime->format('Y_m_d_His');
            $migrationName = "alter_{$table}_sync_columns";

            $alterMigration = <<<MIGPHP
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::table('{$table}', function (Blueprint \$table) {{$upStatements}
                    });
                }

                public function down(): void
                {
                    Schema::table('{$table}', function (Blueprint \$table) {{$downStatements}
                    });
                }
            };
            MIGPHP;

            $alterPath = database_path("migrations/{$alterDate}_{$migrationName}.php");
            File::put($alterPath, $alterMigration);
            Artisan::call('migrate', [
                '--path'  => "database/migrations/{$alterDate}_{$migrationName}.php",
                '--force' => true,
            ]);
        }

        // ── B. Removed Multiple-File Attachment Tables → DROP ───────────────────
        foreach ($removedColumns as $col) {
            $oldField  = $oldFields[$col];
            $inputType = $oldField->column_type_id ?? ($oldField->columnType->column_type_id ?? null);

            if (in_array($inputType, [14, 15]) && $oldField->is_multiple) {
                $attachTable = "{$table}_" . Str::plural($col);
                if (\Schema::hasTable($attachTable)) {
                    \Schema::dropIfExists($attachTable);
                }
            }
        }

        // ── C. New Multiple-File Attachment Tables → CREATE ─────────────────────
        foreach ($module->fields as $field) {
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

            if (in_array($inputType, [14, 15]) && $field->is_multiple) {
                $attachTable = "{$table}_" . Str::plural($field->db_column);

                if (!\Schema::hasTable($attachTable)) {
                    $date = $baseTime->copy()->addSeconds($i)->format('Y_m_d_His');
                    $i++;

                    $attachMigration = <<<MIGPHP
                    <?php

                    use Illuminate\Database\Migrations\Migration;
                    use Illuminate\Database\Schema\Blueprint;
                    use Illuminate\Support\Facades\Schema;

                    return new class extends Migration
                    {
                        public function up(): void
                        {
                            Schema::create('{$attachTable}', function (Blueprint \$table) {
                                \$table->id();
                                \$table->unsignedBigInteger('{$fk}_id');
                                \$table->string('file_name');
                                \$table->string('file_path');
                                \$table->string('mime_type')->nullable();
                                \$table->integer('file_size')->nullable();
                                \$table->timestamps();
                                \$table->foreign('{$fk}_id')
                                    ->references('id')->on('{$table}')
                                    ->cascadeOnDelete();
                            });
                        }

                        public function down(): void
                        {
                            Schema::dropIfExists('{$attachTable}');
                        }
                    };
                    MIGPHP;

                    $path = database_path("migrations/{$date}_create_{$attachTable}.php");
                    File::put($path, $attachMigration);
                    Artisan::call('migrate', [
                        '--path'  => "database/migrations/{$date}_create_{$attachTable}.php",
                        '--force' => true,
                    ]);
                }
            }
        }

        // ── D. Removed Pivot Tables → DROP ──────────────────────────────────────
        $oldPivots = [];
        if ($oldFields) {
            foreach ($oldFields as $col => $oldField) {
                if ($oldField->model_name && $oldField->is_multiple) {
                    $relatedModel = Str::singular($oldField->model_name);
                    $relatedTable = strtolower(Str::plural($relatedModel));
                    $tables       = [$table, $relatedTable];
                    sort($tables);
                    $oldPivots[implode('_', $tables)] = true;
                }
            }
        }

        $newPivots = [];
        foreach ($module->fields as $field) {
            if ($field->model_name && $field->is_multiple) {
                $relatedModel = Str::singular($field->model_name);
                $relatedTable = strtolower(Str::plural($relatedModel));
                $tables       = [$table, $relatedTable];
                sort($tables);
                $newPivots[implode('_', $tables)] = true;
            }
        }

        foreach (array_diff_key($oldPivots, $newPivots) as $pivot => $_) {
            if (\Schema::hasTable($pivot)) {
                \Schema::dropIfExists($pivot);
            }
        }

        // ── E. New Pivot Tables → CREATE ────────────────────────────────────────
        $createdPivots = [];
        foreach ($module->fields as $field) {
            if ($field->model_name && $field->is_multiple) {
                $relatedModel = Str::singular($field->model_name);
                $relatedTable = strtolower(Str::plural($relatedModel));
                $relatedFk    = strtolower(Str::singular($relatedModel));
                $tables       = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                if (in_array($pivot, $createdPivots)) continue;
                $createdPivots[] = $pivot;

                if (!\Schema::hasTable($pivot)) {
                    $date = $baseTime->copy()->addSeconds($i)->format('Y_m_d_His');
                    $i++;

                    $pivotMigration = <<<MIGPHP
                    <?php

                    use Illuminate\Database\Migrations\Migration;
                    use Illuminate\Database\Schema\Blueprint;
                    use Illuminate\Support\Facades\Schema;

                    return new class extends Migration
                    {
                        public function up(): void
                        {
                            Schema::create('{$pivot}', function (Blueprint \$table) {
                                \$table->id();
                                \$table->unsignedBigInteger('{$fk}_id');
                                \$table->unsignedBigInteger('{$relatedFk}_id');
                                \$table->timestamps();
                                \$table->foreign('{$fk}_id')
                                    ->references('id')->on('{$table}')
                                    ->cascadeOnDelete();
                                \$table->foreign('{$relatedFk}_id')
                                    ->references('id')->on('{$relatedTable}')
                                    ->cascadeOnDelete();
                            });
                        }

                        public function down(): void
                        {
                            Schema::dropIfExists('{$pivot}');
                        }
                    };
                    MIGPHP;

                    $path = database_path("migrations/{$date}_create_{$pivot}.php");
                    File::put($path, $pivotMigration);
                    Artisan::call('migrate', [
                        '--path'  => "database/migrations/{$date}_create_{$pivot}.php",
                        '--force' => true,
                    ]);
                }
            }
        }

        // ── F. Regenerate Model ──────────────────────────────────────────────────
        $modelContent  = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\n";
        $modelContent .= "class {$modelName} extends Model\n{\n";
        $modelContent .= "    protected \$table = '{$table}';\n\n";
        $modelContent .= "    protected \$fillable = [";

        foreach ($module->fields as $field) {
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);
            if (!in_array($inputType, [14, 15]) || !$field->is_multiple) {
                $modelContent .= "\n        '{$field->db_column}',";
            }
        }
        $modelContent .= "\n    ];\n";

        // hasMany file relations
        foreach ($module->fields as $field) {
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);
            if (in_array($inputType, [14, 15]) && $field->is_multiple) {
                $relation    = Str::camel(Str::plural($field->db_column));
                $attachModel = $modelName . ucfirst($relation);
                $modelContent .= "\n    public function {$relation}()\n    {\n";
                $modelContent .= "        return \$this->hasMany(\\App\\Models\\{$attachModel}::class, '{$fk}_id');\n    }\n";
            }
        }

        // belongsToMany relations
        $addedRelations = [];
        foreach ($module->fields as $field) {
            if ($field->model_name && $field->is_multiple) {
                $relatedModel = Str::singular($field->model_name);
                $relatedTable = strtolower(Str::plural($relatedModel));
                $relatedFk    = strtolower(Str::singular($relatedModel));
                $tables       = [$table, $relatedTable];
                sort($tables);
                $pivot  = implode('_', $tables);
                $method = Str::plural(Str::camel($relatedFk));

                if (in_array($method, $addedRelations)) continue;
                $addedRelations[] = $method;

                $modelContent .= "\n    public function {$method}()\n    {\n";
                $modelContent .= "        return \$this->belongsToMany(\n";
                $modelContent .= "            \\App\\Models\\{$relatedModel}::class,\n";
                $modelContent .= "            '{$pivot}',\n";
                $modelContent .= "            '{$fk}_id',\n";
                $modelContent .= "            '{$relatedFk}_id'\n";
                $modelContent .= "        )->withTimestamps();\n    }\n";
            }
        }

        $modelContent .= "}\n";
        File::put(app_path("Models/{$modelName}.php"), $modelContent);

        // ── G. Regenerate Controller ─────────────────────────────────────────────
        $controllerName = $modelName . 'Controller';
        $controllerDir  = app_path("Http/Controllers/Modules");

        if (!File::exists($controllerDir)) {
            File::makeDirectory($controllerDir, 0755, true);
        }

        $controllerContent  = "<?php\n\nnamespace App\\Http\\Controllers\\Modules;\n\n";
        $controllerContent .= "use App\\Http\\Controllers\\Controller;\n";
        $controllerContent .= "use App\\Models\\{$modelName};\n";
        $controllerContent .= "use Illuminate\\Http\\Request;\n\n";
        $controllerContent .= "class {$controllerName} extends Controller\n{\n";
        $controllerContent .= "    public function index(Request \$request)\n    {\n";
        $controllerContent .= "        \$data = {$modelName}::latest()->paginate(15);\n";
        $controllerContent .= "        return response()->json(['success' => true, 'data' => \$data]);\n    }\n\n";
        $controllerContent .= "    public function store(Request \$request)\n    {\n";
        $controllerContent .= "        \$record = {$modelName}::create(\$request->all());\n";
        $controllerContent .= "        return response()->json(['success' => true, 'data' => \$record], 201);\n    }\n\n";
        $controllerContent .= "    public function show(\$id)\n    {\n";
        $controllerContent .= "        \$record = {$modelName}::findOrFail(\$id);\n";
        $controllerContent .= "        return response()->json(['success' => true, 'data' => \$record]);\n    }\n\n";
        $controllerContent .= "    public function update(Request \$request, \$id)\n    {\n";
        $controllerContent .= "        \$record = {$modelName}::findOrFail(\$id);\n";
        $controllerContent .= "        \$record->update(\$request->all());\n";
        $controllerContent .= "        return response()->json(['success' => true, 'data' => \$record]);\n    }\n\n";
        $controllerContent .= "    public function destroy(\$id)\n    {\n";
        $controllerContent .= "        {$modelName}::findOrFail(\$id)->delete();\n";
        $controllerContent .= "        return response()->json(['success' => true, 'message' => 'Deleted successfully']);\n    }\n";
        $controllerContent .= "}\n";

        File::put("{$controllerDir}/{$controllerName}.php", $controllerContent);

        // ── H. Module Directories ────────────────────────────────────────────────
        // $fileService = new ModuleFileStructureService();
        // $fileService->createModuleDirectories($module->slug);
        // $fileService->createGitkeepFiles($module->slug);
    }

    public function destroyWithFields($id)
    {
        $module     = Module::findOrFail($id);
        $moduleSlug = $module->slug;
        $modelName  = $module->main_model_name;
        $table      = strtolower(Str::plural($moduleSlug));

        // ── PHASE 1: DB Transaction ─────────────────────────────────────────────
        try {
            DB::beginTransaction();

            // 1. Module Permissions delete
            $module->permissions()->delete();

            // 2. Spatie permissions delete (sanctum guard)
            $allPermissionActions = ['access', 'create', 'edit', 'show', 'delete'];
            foreach ($allPermissionActions as $action) {
                $permName   = $moduleSlug . '_' . $action;
                $permission = Permission::where('name', $permName)
                                ->where('guard_name', 'sanctum')
                                ->first();

                if ($permission) {
                    // Roles thi permission detach karo
                    $permission->roles()->detach();
                    // Users thi permission detach karo
                    $permission->users()->detach();
                    // Permission delete karo
                    $permission->delete();
                }
            }

            // 3. Assignments detach
            $module->assignedAdmins()->detach();
            $module->assignedAgencies()->detach();

            // 4. Fields + Options (cascade delete)
            // Fields load karo file/pivot info mate — delete PEHLA
            $fields = $module->fields()->with('columnType')->get();

            $module->fields()->each(function ($field) {
                $field->options()->delete();
            });
            $module->fields()->delete();

            // 5. Module delete
            $module->delete();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'DB Error: ' . $e->getMessage()
            ], 500);
        }

        // ── PHASE 2: Schema + File Cleanup (Transaction BAHAR) ──────────────────
        try {
            $this->deleteModuleFiles($fields, $table, $moduleSlug, $modelName);
        } catch (\Exception $e) {
            // Module DB thi delete thai gayo, file cleanup warning aape
            return response()->json([
                'success' => true,
                'message' => 'Module deleted successfully (cleanup warning: ' . $e->getMessage() . ')'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }

    private function deleteModuleFiles($fields, $table, $moduleSlug, $modelName)
    {
        $fk = strtolower(Str::singular($moduleSlug));

        // ── A. Drop Main Table ───────────────────────────────────────────────────
        if (\Schema::hasTable($table)) {
            \Schema::dropIfExists($table);
        }

        // ── B. Drop Multiple-File Attachment Tables ──────────────────────────────
        foreach ($fields as $field) {
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

            if (in_array($inputType, [14, 15]) && $field->is_multiple) {
                $attachTable = "{$table}_" . Str::plural($field->db_column);

                if (\Schema::hasTable($attachTable)) {
                    \Schema::dropIfExists($attachTable);
                }
            }
        }

        // ── C. Drop Pivot Tables ─────────────────────────────────────────────────
        $droppedPivots = [];
        foreach ($fields as $field) {
            if ($field->model_name && $field->is_multiple) {
                $relatedModel = Str::singular($field->model_name);
                $relatedTable = strtolower(Str::plural($relatedModel));

                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                if (in_array($pivot, $droppedPivots)) continue;
                $droppedPivots[] = $pivot;

                if (\Schema::hasTable($pivot)) {
                    \Schema::dropIfExists($pivot);
                }
            }
        }

        // ── D. Delete All Related Migration Files ────────────────────────────────
        $this->deleteModuleMigrationFiles($table, $fields);

        // ── E. Delete Model File ─────────────────────────────────────────────────
        $modelPath = app_path("Models/{$modelName}.php");
        if (File::exists($modelPath)) {
            File::delete($modelPath);
        }

        // ── F. Delete Controller File ────────────────────────────────────────────
        $controllerPath = app_path("Http/Controllers/Modules/{$modelName}Controller.php");
        if (File::exists($controllerPath)) {
            File::delete($controllerPath);
        }

        // ── G. Delete Module Directories ─────────────────────────────────────────
        $fileService = new ModuleFileStructureService();
        $fileService->deleteModuleDirectories($moduleSlug);
    }

    private function deleteModuleMigrationFiles($table, $fields)
    {
        $migrationPath = database_path('migrations');
        $deletedFiles  = [];
        $skippedFiles  = [];

        // ── Step 1: Collect all table names that belong to this module ───────────
        $relatedTableNames = [];

        // Main table
        $relatedTableNames[] = $table;

        // Attachment tables  (e.g. features_images, features_documents)
        foreach ($fields as $field) {
            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

            if (in_array($inputType, [14, 15]) && $field->is_multiple) {
                $relatedTableNames[] = "{$table}_" . Str::plural($field->db_column);
            }
        }

        // Pivot tables  (e.g. features_users, features_roles)
        $addedPivots = [];
        foreach ($fields as $field) {
            if ($field->model_name && $field->is_multiple) {
                $relatedModel = Str::singular($field->model_name);
                $relatedTable = strtolower(Str::plural($relatedModel));

                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                if (in_array($pivot, $addedPivots)) continue;
                $addedPivots[] = $pivot;

                $relatedTableNames[] = $pivot;
            }
        }

        // ── Step 2: Get all migration files ──────────────────────────────────────
        $allMigrationFiles = File::files($migrationPath);

        foreach ($allMigrationFiles as $file) {
            $filename = $file->getFilename();

            // Migration filename pattern:
            // 2024_01_01_000000_create_features.php
            // 2024_01_01_000001_create_features_images.php
            // 2024_01_01_000002_alter_features_sync_columns.php
            // 2024_01_01_000003_alter_features_add_columns.php

            foreach ($relatedTableNames as $relatedTable) {
                $matched = false;

                // CREATE migration  →  _create_{table}.php  or  _create_{table}_...
                if (
                    str_contains($filename, "_create_{$relatedTable}.php") ||
                    str_contains($filename, "_create_{$relatedTable}_")
                ) {
                    $matched = true;
                }

                // ALTER migration  →  _alter_{table}_
                if (str_contains($filename, "_alter_{$relatedTable}_")) {
                    $matched = true;
                }

                if ($matched) {
                    try {
                        File::delete($file->getPathname());
                        $deletedFiles[] = $filename;
                    } catch (\Exception $e) {
                        $skippedFiles[] = $filename . ' (' . $e->getMessage() . ')';
                    }

                    // Ek file ek j table mate match thay — next file par jao
                    break;
                }
            }
        }

        // ── Step 3: Log deleted/skipped files (optional debug) ───────────────────
        if (!empty($deletedFiles)) {
            \Log::info("Module [{$table}] migration files deleted:", $deletedFiles);
        }

        if (!empty($skippedFiles)) {
            \Log::warning("Module [{$table}] migration files could not be deleted:", $skippedFiles);
        }
    }

    public function deleteField($id)
    {
        $field = ModuleField::findOrFail($id);
        $field->delete();
        return response()->json(['success' => true, 'message' => 'Field deleted successfully']);
    }

    public function deleteFieldOption($id)
    {
        $option = ModuleFieldOption::findOrFail($id);
        $option->delete();
        return response()->json(['success' => true, 'message' => 'Field option deleted successfully']);
    }

    public function reorderFields(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fields'                => 'required|array',
            'fields.*.id'           => 'required|integer',
            'fields.*.order_number' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        foreach ($request->input('fields') as $fieldData) {
            ModuleField::where('id', $fieldData['id'])->update(['order_number' => $fieldData['order_number']]);
        }

        return response()->json(['success' => true, 'message' => 'Order updated successfully']);
    }

    public function updateFieldStatus(Request $request, $id)
    {
        $field = ModuleField::findOrFail($id);
        $field->update(['status' => $request->input('status', true)]);
        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function updateModuleStatus(Request $request, $id)
    {
        $module = Module::findOrFail($id);
        $module->update(['status' => $request->input('status', true)]);
        return response()->json(['success' => true, 'message' => 'Status updated successfully']);
    }

    public function updateFieldOptions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module_id'       => 'required|integer',
            'module_field_id' => 'required|integer',
            'column_type_id'  => 'required|integer',
            'options'         => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Delete existing options
        ModuleFieldOption::where('module_field_id', $request->input('module_field_id'))->delete();

        // Add new options
        foreach ($request->input('options') as $option) {
            ModuleFieldOption::create([
                'module_field_id' => $request->input('module_field_id'),
                'option_label'    => $option['option_label'],
                'option_value'    => $option['option_value'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Option updated successfully']);
    }

    private function generateModuleFiles($module)
{
    $modelName = $module->main_model_name;
    $table = strtolower(Str::plural($module->slug)); // projects
    $fk    = strtolower(Str::singular($module->slug)); // project
    $baseTime = now();

    $mainMigrations = [];
    $fileMigrations = [];
    $pivotMigrations = [];

    /*
    |--------------------------------------------------
    | MAIN TABLE
    |--------------------------------------------------
    */
    $mainDate = $baseTime->format('Y_m_d_His');

    $migrationContent = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
PHP;

    foreach ($module->fields as $field) {

        $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);
        $type = $field->columnType->db_type ?? 'string';

        // BELONGS TO
        if (!$field->is_multiple && $field->model_name) {
            $migrationContent .= "\n    \$table->unsignedBigInteger('{$field->db_column}')->nullable();";
            continue;
        }

        // NORMAL FIELD
        if (!in_array($inputType, [14,15])) {
            $migrationContent .= "\n    \$table->{$type}('{$field->db_column}')->nullable();";
        }

        // SINGLE FILE
        if (in_array($inputType, [14,15]) && !$field->is_multiple) {
            $migrationContent .= "\n    \$table->string('{$field->db_column}')->nullable();";
        }
    }

    $migrationContent .= <<<PHP

            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

    $mainPath = database_path("migrations/{$mainDate}_create_{$table}.php");
    File::put($mainPath, $migrationContent);
    $mainMigrations[] = "database/migrations/{$mainDate}_create_{$table}.php";

    /*
    |--------------------------------------------------
    | FILE TABLES (FIXED)
    |--------------------------------------------------
    */
    $i = 1;

    foreach ($module->fields as $field) {

        $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

        if (in_array($inputType, [14, 15]) && $field->is_multiple) {

            $attachTable = "{$table}_" . Str::plural($field->db_column);
            $date = $baseTime->copy()->addSeconds($i)->format('Y_m_d_His');
            $i++;

            $migration = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$attachTable}', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('{$fk}_id');
            \$table->string('file_name');
            \$table->string('file_path');
            \$table->string('mime_type')->nullable();
            \$table->integer('file_size')->nullable();
            \$table->timestamps();

            \$table->foreign('{$fk}_id')
                ->references('id')
                ->on('{$table}')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$attachTable}');
    }
};
PHP;

            $path = database_path("migrations/{$date}_create_{$attachTable}.php");
            File::put($path, $migration);
            $fileMigrations[] = "database/migrations/{$date}_create_{$attachTable}.php";
        }
    }

    /*
    |--------------------------------------------------
    | PIVOT TABLES
    |--------------------------------------------------
    */
    $createdPivots = [];

    foreach ($module->fields as $field) {

        if ($field->model_name && $field->is_multiple) {

            $relatedModel = Str::singular($field->model_name);
            $relatedTable = strtolower(Str::plural($relatedModel));
            $relatedFk = strtolower(Str::singular($relatedModel));

            $tables = [$table, $relatedTable];
            sort($tables);
            $pivot = implode('_', $tables);

            if (in_array($pivot, $createdPivots)) continue;

            $createdPivots[] = $pivot;

            $date = $baseTime->copy()->addSeconds($i)->format('Y_m_d_His');
            $i++;

            $migration = <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$pivot}', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('{$fk}_id');
            \$table->unsignedBigInteger('{$relatedFk}_id');
            \$table->timestamps();

            \$table->foreign('{$fk}_id')
                ->references('id')
                ->on('{$table}')
                ->cascadeOnDelete();

            \$table->foreign('{$relatedFk}_id')
                ->references('id')
                ->on('{$relatedTable}')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$pivot}');
    }
};
PHP;

            $path = database_path("migrations/{$date}_create_{$pivot}.php");
            File::put($path, $migration);
            $pivotMigrations[] = "database/migrations/{$date}_create_{$pivot}.php";
        }
    }

    /*
    |--------------------------------------------------
    | MODEL
    |--------------------------------------------------
    */
    $modelContent = <<<PHP
<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class {$modelName} extends Model
{
    protected \$table = '{$table}';

    protected \$fillable = [
PHP;

    foreach ($module->fields as $field) {

        $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

        if (!in_array($inputType, [14,15]) || !$field->is_multiple) {
            $modelContent .= "\n        '{$field->db_column}',";
        }
    }

    $modelContent .= "\n    ];\n";

    // FILE RELATIONS
    foreach ($module->fields as $field) {

        $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

        if (in_array($inputType, [14,15]) && $field->is_multiple) {

            $relation = Str::camel(Str::plural($field->db_column));
            $attachTable = "{$table}_" . Str::plural($field->db_column);

            $modelContent .= <<<PHP

    public function {$relation}()
    {
        return \$this->hasMany(\\App\\Models\\{$modelName}{$relation}::class, '{$fk}_id');
    }
PHP;
        }
    }

    // PIVOT RELATIONS
    foreach ($module->fields as $field) {

        if ($field->model_name && $field->is_multiple) {

            $relatedModel = Str::singular($field->model_name);
            $relatedTable = strtolower(Str::plural($relatedModel));
            $relatedFk = strtolower(Str::singular($relatedModel));

            $tables = [$table, $relatedTable];
            sort($tables);
            $pivot = implode('_', $tables);

            $method = Str::plural(Str::camel($relatedFk));

            $modelContent .= <<<PHP

    public function {$method}()
    {
        return \$this->belongsToMany(
            \\App\\Models\\{$relatedModel}::class,
            '{$pivot}',
            '{$fk}_id',
            '{$relatedFk}_id'
        )->withTimestamps();
    }
PHP;
        }
    }

    $modelContent .= "\n}\n";

    File::put(app_path("Models/{$modelName}.php"), $modelContent);

    /*
    |--------------------------------------------------
    | RUN MIGRATIONS
    |--------------------------------------------------
    */
    foreach ($mainMigrations as $path) {
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }

    foreach ($fileMigrations as $path) {
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }

    foreach ($pivotMigrations as $path) {
        Artisan::call('migrate', ['--path' => $path, '--force' => true]);
    }
}
}
