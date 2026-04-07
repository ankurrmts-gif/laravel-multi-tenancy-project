<?php

namespace Modules\UserManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    private function runInTenant(?string $tenantId, \Closure $callback)
    {
        if ($tenantId) {
            $tenant = Tenant::findOrFail($tenantId);
            tenancy()->initialize($tenant);
            // When running inside a tenant, use the tenant guard by default
            config(['auth.defaults.guard' => 'tenant_api']);

            $result = $callback();

            // reset to central guard after tenant work
            tenancy()->end();
            config(['auth.defaults.guard' => 'sanctum']);
            return $result;
        }

        // Ensure central/default guard is used for central DB operations
        config(['auth.defaults.guard' => 'sanctum']);

        return $callback(); // Central DB
    }

    public function index(Request $request): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($request) {

            $permission = $request->filled('tenant_id')
                ? 'tenant-role-access'
                : 'role-access';

            // Clear permission cache (debug purpose)
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Permission check (optional)
            // if (!$request->user()->can($permission)) {
            //     return response()->json(['message' => 'Access Denied'], 403);
            // }

            /*
            |--------------------------------------------------------------------------
            | SELECT MODE (dropdown)
            |--------------------------------------------------------------------------
            */
            
            $user = $request->user();
            if ($request->select == true) {

                if ($user && $user->tenant_id) {

                    tenancy()->initialize($user->tenant_id);

                    $roles = Role::select('id', 'name')
                        ->orderBy('name')
                        ->get();

                    tenancy()->end();

                    return response()->json([
                        'roles' => $roles
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | filter by type
                |--------------------------------------------------------------------------
                */
                if ($request->filled('type')) {

                    $roles = Role::select('id', 'name', 'type')
                        ->where('type', $request->type)
                        ->orderBy('name')
                        ->get();

                    return response()->json([
                        'roles' => $roles
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | filter based on logged-in user type
                |--------------------------------------------------------------------------
                */

                if ($user && $user->user_type == 'admin') {

                    $roles = Role::select('id', 'name', 'type')
                        ->where('type', 'agency')
                        ->orderBy('name')
                        ->get();

                    return response()->json([
                        'roles' => $roles
                    ]);
                }

                if ($user && $user->user_type == 'super_admin') {

                    $roles = Role::select('id', 'name', 'type')
                        ->where('type', 'admin')
                        ->orderBy('name')
                        ->get();

                    return response()->json([
                        'roles' => $roles
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | default select
                |--------------------------------------------------------------------------
                */
                $roles = Role::select('id', 'name', 'type')
                    ->orderBy('name')
                    ->get();

                return response()->json([
                    'roles' => $roles
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | LIST MODE (pagination)
            |--------------------------------------------------------------------------
            */

            $limit = $request->limit ?? 10;
            $sort  = $request->sort ?? 'created_at';
            $dir   = $request->dir ?? 'desc';

            $query = Role::with(['permissions:id,name'])
                ->select('id', 'name', 'type', 'created_at');

            /*
            |--------------------------------------------------------------------------
            | search filter
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {

                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('permissions', function ($q2) use ($search) {

                            $q2->where('name', 'like', "%{$search}%");

                        });

                });
            }

            /*
            |--------------------------------------------------------------------------
            | optional type filter
            |--------------------------------------------------------------------------
            */
            if ($request->filled('type')) {

                $query->where('type', $request->type);
            }

            $roles = $query
                ->orderBy($sort, $dir)
                ->paginate($limit);

            /*
            |--------------------------------------------------------------------------
            | hide pivot
            |--------------------------------------------------------------------------
            */
            $roles->getCollection()->transform(function ($role) {

                $role->permissions->each->makeHidden('pivot');

                return $role;

            });

            return response()->json([
                'roles' => $roles
            ]);

        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($request) {
            $permission = $request->filled('tenant_id')
                ? 'tenant-role-create'
                : 'role-create';

            // Clear permission cache (temporary for debug)
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();   

            // if (!$request->user()->can($permission)) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }

            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name',
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'sanctum',
                'type' => $request->type ? $request->type : NULL,
            ]);

            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            Cache::tags(['roles_list'])->flush();

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role->load('permissions')
            ], 201);
        });
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($request, $id) {
            $permission = $request->filled('tenant_id')
                ? 'tenant-role-show'
                : 'role-show';

            // Clear permission cache (temporary for debug)
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();   

            // if (!$request->user()->can($permission)) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }

            $role = Role::with(['permissions:id,name'])->select('id', 'name','type', 'created_at')->find($id);

            if (!$role) {
                return response()->json(['message' => 'Role not found'], 404);
            }

            $role->permissions->each->makeHidden('pivot');
        
            return response()->json([
                'role' => $role
            ]);
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($request, $id) {

            $permission = $request->filled('tenant_id')
                ? 'tenant-role-edit'
                : 'role-edit';

            // Clear permission cache (temporary for debug)
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();   

            // if (!$request->user()->can($permission)) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }

            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name,' . $id,
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role = Role::findOrFail($id);
            $role->update(['name' => $validated['name']]);

            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            Cache::tags(['roles_list'])->flush();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $role->load('permissions')
            ]);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($id) {
            $permission = $request->filled('tenant_id')
                ? 'tenant-role-delete'
                : 'role-delete';

            // Clear permission cache (temporary for debug)
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();   

            // if (!$request->user()->can($permission)) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }

            $role = Role::find($id);
            if (!$role) {
                return response()->json(['message' => 'Role not found'], 404);
            }
            $role->delete();

            Cache::tags(['roles_list'])->flush();

            return response()->json([
                'message' => 'Role deleted successfully'
            ]);
        });
    }

    public function assignToUser(Request $request, int $roleId): JsonResponse
    {
        return $this->runInTenant($request->tenant_id, function () use ($request, $roleId) {

            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = User::findOrFail($validated['user_id']);
            $role = Role::findOrFail($roleId);

            $user->assignRole($role);

            return response()->json([
                'message' => 'Role assigned to user successfully',
                'user' => $user->load('roles.permissions')
            ]);
        });
    }
}
