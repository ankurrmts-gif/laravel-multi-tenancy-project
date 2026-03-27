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

            $modules = Module::where('status', true)->orderBy('order_number')->get();

            $allowed = $modules->filter(fn($module) => $this->userCanAccessModule($module, $user));

            $tree  = [];
            $items = [];

            foreach ($allowed as $module) {
                $items[$module->id] = [
                    'id'          => $module->id,
                    'menu_title'  => $module->menu_title,
                    'slug'        => $module->slug,
                    'icon'        => $module->icon,
                    'parent_menu' => $module->parent_menu,
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

        // user_type restrictions
        if (! empty($module->user_type) && $module->user_type !== 'all' && $module->user_type !== $user->user_type) {
            return false;
        }

        $permissionName = $module->slug . '_access';
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
            'module.order_number'          => 'integer',
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

        foreach ($allPermissions as $permission) {

            ModulePermission::updateOrCreate([
                'module_id' => $module->id,
                'user_id' => $user->id,
                'permission_name' => $permission->name
            ]);
        }


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

                        foreach ($permissions as $permission) {

                            ModulePermission::updateOrCreate([
                                'module_id' => $module->id,
                                'user_id' => $adminId,
                                'permission_name' => $permission->name
                            ]);
                        }
                    }
                }

                elseif (($moduleData['assigned_admins'] ?? '') === 'all') {

                    $role = Role::where('name', 'admin')->first();

                    if ($role) {
                        $role->givePermissionTo($permissions);
                    }
                }
            }


            elseif ($moduleData['user_type'] === 'agency') {

                if (!empty($moduleData['assigned_agencies']) && is_array($moduleData['assigned_agencies'])) {

                    foreach ($moduleData['assigned_agencies'] as $agency) {

                        $agencyId = is_array($agency) ? $agency['id'] : $agency;

                        foreach ($permissions as $permission) {

                            ModulePermission::updateOrCreate([
                                'module_id' => $module->id,
                                'user_id' => $agencyId,
                                'permission_name' => $permission->name
                            ]);
                        }
                    }
                }

                elseif (($moduleData['assigned_agencies'] ?? '') === 'all') {

                    $role = Role::where('name', 'agency')->first();

                    if ($role) {
                        $role->givePermissionTo($permissions);
                    }
                }
            }


            elseif ($moduleData['user_type'] === 'all') {

                foreach (['admin','agency'] as $roleName) {

                    $role = Role::where('name', $roleName)->first();

                    if ($role) {
                        $role->givePermissionTo($permissions);
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

        $fileService = new ModuleFileStructureService();
        $fileService->createModuleDirectories($module->slug);
        $fileService->createGitkeepFiles($module->slug);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully'
        ]);
    }

    public function show($id)
    {
        $module = Module::with(['fields.options', 'assignedAdmins', 'assignedAgencies', 'permissions'])->findOrFail($id);
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

    public function updateWithFields(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'fields'                  => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
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

        $fileService = new ModuleFileStructureService();
        $fileService->createModuleDirectories($module->slug);
        $fileService->createGitkeepFiles($module->slug);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully'
        ]);   
    }

    public function destroyWithFields($id)
    {
        $module     = Module::findOrFail($id);
        $moduleSlug = $module->slug;

        $module->delete(); // Cascades

        // Clean up module directories
        $fileService = new ModuleFileStructureService();
        $fileService->deleteModuleDirectories($moduleSlug);

        return response()->json(['success' => true, 'message' => 'Module deleted successfully']);
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
