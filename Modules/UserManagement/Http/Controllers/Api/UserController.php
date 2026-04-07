<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;
 
use App\Models\User;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;
use Stancl\Tenancy\Database\Models\Domain as TenancyDomain;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Models\Tenant,App\Models\CentralTenantTelations,App\Models\UserInvitations;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
 
class UserController extends Controller
{  
    public function getUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [ 
            'tenant_id' => 'nullable',
            'search'    => 'nullable|string|max:255',
            'limit'     => 'nullable|integer',
            'sort'      => 'nullable|string',
            'dir'       => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Permission mapping
        // $permissionMap = [
        //     'admin'  => 'admin-access',
        //     'agency' => 'agency-access',
        // ];

        // if ($request->user_type && isset($permissionMap[$request->user_type])) {
        //     if (!$request->user()->can($permissionMap[$request->user_type])) {
        //         return response()->json(['message' => 'Access Denied.'], 403);
        //     }
        // }
        $Auth = $request->user();
        
        //echo '<pre>'; print_r($Auth); echo '</pre>'; die();

        $cacheKey = 'users_list_'.$Auth->user_type.'_'.$Auth->id . '_' . md5($request->fullUrl());
        $users = Cache::tags(['users_list_'.$Auth->user_type.'_'.$Auth->ids])->remember($cacheKey,300, function () use ($request) {
            $limit = $request->limit ?? 10;
            $sort  = $request->sort ?? 'created_at';
            $dir   = $request->dir ?? 'desc';

            $Auth = $request->user();


            $tenant_id = CentralTenantTelations::on('mysql')
                ->where('email', $Auth->email)
                ->value('tenant_id');
            /*
            |--------------------------------------------------------------------------
            | If tenant_id exists → fetch tenant agents
            |--------------------------------------------------------------------------
            */

            if ($request->filled('tenant_id') || !empty($tenant_id)){
                // if (!$request->user()->can('agent-access')) {
                //     return response()->json(['message' => 'Access Denied.'], 403);
                // }
                $tenant = Tenant::find($request->tenant_id ?? $tenant_id);

                if (!$tenant) {
                    return response()->json(['message' => 'Agency not found.'], 404);
                }

                tenancy()->initialize($tenant->id);


                $users = User::select('id', 'first_name', 'last_name', 'email', 'created_by','google2fa_secret', 'created_at');

                //echo '<pre>'; print_r($users->get()); echo '</pre>'; die();

                if ($request->filled('search')) {
                    $search = $request->search;

                    $users->where(function ($q) use ($search) {
                        $q->where('first_name','like',"%{$search}%")
                        ->orWhere('last_name','like',"%{$search}%")
                        ->orWhere('email','like',"%{$search}%");
                    });
                }

                //tenancy()->end();
                
                return $users->where('created_by', $Auth->id)->orderBy($sort,$dir)->paginate($limit);
            }
        
            /*
            |--------------------------------------------------------------------------
            | Central Users (Admin / Agency)
            |--------------------------------------------------------------------------
            */

            $users = User::select('id', 'first_name', 'last_name', 'email', 'created_by', 'user_type', 'google2fa_secret', 'created_at')->where('id', '!=', $Auth->id);
            
            $users = $users->whereHas('roles', function ($q) use ($request) {
                $q->where('user_type','!=','super_admin');

                if ($request->user_type) {
                    $q->where('name',$request->user_type);
                }
            });

            if($Auth->user_type != 'super_admin'){
                $users->where('created_by',$Auth->id);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $users->where(function ($q) use ($search) {
                    $q->where('first_name','like',"%{$search}%")
                    ->orWhere('last_name','like',"%{$search}%")
                    ->orWhere('email','like',"%{$search}%");
                });
            }

            return $users->orderBy($sort,$dir)->paginate($limit);
        });

        return response()->json([
            'status' => true,
            'users'  => $users
        ],200);
    }
 
    public function getUserDetails(Request $request)
    {
        $Auth = $request->user();
 
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
 
        if($request->filled('tenant_id')){
            // if (!$request->user()->can('agent-show')) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }
            $tenant = Tenant::find($request->tenant_id);
 
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found.'], 404);
            }
 
            tenancy()->initialize($tenant);
            $user = User::find($request->id);

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            
            // $user->load('roles.permissions','tenant');
            $user->role_id = $user->roles->first() ? $user->roles->first()->id : null;

            tenancy()->end();
        } else {
            $user = User::select('id', 'first_name', 'last_name', 'email', 'email_verified_at', 'user_type', 'tenant_id', 'created_at')->find($request->id);
 
            // if($request->user_type == 'admin'){
            //     $permissionName = 'admin-show';
            // } else {
            //     $permissionName = 'agency-show';
            // }
 
            // if (!$request->user()->can($permissionName)) {
            //     return response()->json(['message' => 'Access Denied.'], 403);
            // }
       
            // $user->load('roles.permissions','tenant');
             $user->role_id = $user->roles->first() ? $user->roles->first()->id : null;
        }
       
        return response()->json([
            'user' => $user
        ],200);
    }
 
    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'    => 'required|integer|exists:users,id',
            'first_name'  => 'nullable|string',
            'last_name'  => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'role_id' => 'nullable|integer',
        ]);
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
 
        // TENANT USER UPDATE
        if ($request->filled('tenant_id')) {
 
            if (!$request->user()->can('agent-edit')) {
                return response()->json(['message' => 'Access Denied.'], 403);
            }
 
            $tenant = Tenant::find($request->tenant_id);
            tenancy()->initialize($tenant->id);
 
            $user = User::find($request->id);
 
            if (!$user) {
                tenancy()->end();
                return response()->json(['message' => 'User not found.'], 404);
            }
 
            $user->update([
                'first_name'  => $request->first_name ?? $user->first_name,
                'last_name'  => $request->last_name ?? $user->last_name,
                'email' => $request->email ?? $user->email,
            ]);

            if ($request->filled('role_id')) {
                $role = Role::find($request->role_id);

                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }
 
            tenancy()->end();
 
        }
        // CENTRAL USER UPDATE
        else {
 
            $user = User::find($request->id);
 
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
 
            // Decide permission based on ACTUAL user role
            if ($user->hasRole('admin')) {
                $permissionName = 'admin-edit';
            } else {
                $permissionName = 'agency-edit';
            }
 
            if (!$request->user()->can($permissionName)) {
                return response()->json(['message' => 'Access Denied.'], 403);
            }
 
            $user->update([
                'first_name'  => $request->first_name ?? $user->first_name,
                'last_name'  => $request->last_name ?? $user->last_name,
                'email' => $request->email ?? $user->email,
            ]);

            if ($request->filled('role_id')) {
                $role = Role::find($request->role_id);

                if ($role) {
                    $user->syncRoles([$role->name]);
                }
            }
 
            if ($request->filled('agency_name') && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                if ($tenant) {
                    $tenant->update([
                        'agency_name' => $request->agency_name,
                    ]);
                }
            }
        }

        Cache::tags(['agency_list'])->flush();
        Cache::tags(['users_list'])->flush();
        Cache::tags(['agents_list'])->flush();
 
        return response()->json([
            'status'  => true,
            'message' => 'User updated successfully.',
        ], 200);
    }

    public function deleteUser(Request $request, $userId): JsonResponse
    {
        $tenantId = $request->tenant_id ?? null;

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | CASE 1: Only Tenant Delete (id + tenant_id)
            |--------------------------------------------------------------------------
            */
            if ($tenantId) {

                $tenant = Tenant::find($tenantId);

                if (!$tenant) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Tenant not found.'
                    ], 404);
                }

                tenancy()->initialize($tenant);

                $tenantUser = User::find($userId);

                if (!$tenantUser) {
                    tenancy()->end();

                    return response()->json([
                        'status' => false,
                        'message' => 'User not found.'
                    ], 404);
                }

                $tenantUser->delete();

                tenancy()->end();

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'User deleted successfully.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CASE 2: Central Delete (Only ID)
            |--------------------------------------------------------------------------
            */

            tenancy()->end(); // Ensure central DB

            $centralUser = User::find($userId);

            if (!$centralUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            // Soft delete central
            $centralUser->delete();

            // Deactivate relation
            CentralTenantTelations::where('email', $centralUser->email)
                ->update(['status' => 'deactive']);

            /*
            |--------------------------------------------------------------------------
            | If user has agency role → delete from all tenants
            |--------------------------------------------------------------------------
            */

            if ($centralUser->hasRole('agency')) {

                $tenants = Tenant::all();

                foreach ($tenants as $tenant) {

                    tenancy()->initialize($tenant);

                    $tenantUser = User::where('email', $centralUser->email)->first();

                    if ($tenantUser) {
                        $tenantUser->delete();
                    }

                    tenancy()->end();
                }
            }

            DB::commit();

            Cache::tags(['agency_list'])->flush();
            Cache::tags(['users_list'])->flush();
            Cache::tags(['agents_list'])->flush();

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();
            tenancy()->end();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAgency(Request $request)
    {
        $tenant = Tenant::select('id', 'agency_name')->get();
 
        return response()->json([
            'agency' => $tenant
        ],200);
    }

    public function getAdmin(Request $request)
    {
        $admin = User::select('id', 'first_name', 'last_name')->where('user_type','admin')->get();
 
        return response()->json([
            'admin' => $admin
        ],200);
    }

    public function resetMfa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);        
 
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }
 
        if($request->filled('tenant_id')){
            $tenant = Tenant::find($request->tenant_id);
 
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found.'], 404);
            }
 
            tenancy()->initialize($tenant);
 
        }
        $user = User::find($request->user_id);
 
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }
 
        // Reset MFA for the user
        $user->google2fa_secret = NULL;
        $user->save();
 
        return response()->json([
            'status' => true,
            'message' => 'MFA reset successfully.'
        ]);
    }

    public function getAllUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [ 
            'type'    => 'required|in:admin,customer,users',
            'search'    => 'nullable|string|max:255',
            'limit'     => 'nullable|integer',
            'sort'      => 'nullable|string',
            'dir'       => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cacheKey = 'all_users_list_' . md5($request->fullUrl());
        $users = Cache::tags(['all_users_list'])->rememberForever($cacheKey, function () use ($request) {
            $limit = $request->limit ?? 10;
            $sort  = $request->sort ?? 'created_at';
            $dir   = $request->dir ?? 'desc';

            /*
            |--------------------------------------------------------------------------
            | If tenant_id exists → fetch tenant agents
            |--------------------------------------------------------------------------
            */

            $Auth = $request->user();
            $users = User::select('id', 'first_name', 'last_name', 'email', 'user_type', 'created_at')->where('id', '!=', $Auth->id);
            
            $users = $users->whereHas('roles', function ($q) use ($request) {
                $q->where('user_type','!=','super_admin');

                if ($request->user_type) {
                    $q->where('name',$request->user_type);
                }
            });

            if ($request->filled('search')) {
                $search = $request->search;

                $users->where(function ($q) use ($search) {
                    $q->where('first_name','like',"%{$search}%")
                    ->orWhere('last_name','like',"%{$search}%")
                    ->orWhere('email','like',"%{$search}%");
                });

            }

            if($request->type != 'users'){
                $users->where('user_type',$request->type);
            }else{
                $Tenant = Tenant::select('id')->get();
                $allusers = [];
                foreach($Tenant as $tenant){
                    tenancy()->initialize($tenant->id);
                    $tenantUsers = User::select('id', 'first_name', 'last_name', 'email', 'user_type', 'created_at')->get();
                    $allusers = array_merge($allusers, $tenantUsers->toArray());
                    tenancy()->end();
                }
                return collect($allusers)->paginate($limit);
            }

            return $users->orderBy($sort,$dir)->paginate($limit);
        });

        return response()->json([
            'status' => true,
            'users'  => $users
        ],200);
    }
}