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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
 
class InvitateUserController extends Controller
{  
    public function invite(Request $request): JsonResponse
    {
        $user_id = $request->user()->id;
        $user = User::find($user_id);
 
        // Base validation
        $rules = [
            'first_name'  => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'role_id'  => 'required',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
                Rule::unique('super_admin_invitations', 'email'),
                Rule::unique('central_tenant_relations', 'email'),
            ],
        ];

        $validator = Validator::make($request->all(), $rules);
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
 
        $Settings = Settings::where('key','expired_link_duration')->first();
        $expireDays = (int)$Settings->value ?? 1;

        if($user->user_type === 'super_admin'){
           $user_type = 'admin';
        }elseif($user->user_type === 'admin'){
            $user_type = 'agency';
        }else{
            $user_type = 'agent';
        }
        
        // Create invitation
        $invitation = UserInvitations::create([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'role_id'      => $request->role_id,
            'password'   => Hash::make(bin2hex(random_bytes(6))),
            'token'      => Str::random(64),
            'user_type'  => $user_type,
            'status'     => 'pending',
            'tenant_id'  => $user_type === 'agent' ? $request->tenant_id : null,
            'expires_at' => now()->addDays($expireDays),
            'created_by' => $user->id,
        ]);
 
        $inviter = $user;
 
        $payload = [
            'token' => $invitation->token,
            'email' => $invitation->email,
            'first_name'  => $request->first_name,
            'last_name'  => $request->last_name
        ];
 
        $encrypted = Crypt::encryptString(json_encode($payload));
 
        $frontendUrl = config('app.frontend_url'). 'accept-invitation?data=' . urlencode($encrypted);
        
        // Send email once
        Mail::to($request->email)->send(new \App\Mail\UserInvitationMail($invitation, $inviter,$frontendUrl));

        Cache::tags(['invitation_list_user_' . $user->id])->flush();

        return response()->json([
            'message' => 'Invitation sent successfully.'
        ]);
    }

    public function resendInvite(Request $request): JsonResponse
    {
        $user_id = $request->user()->id;
        $user = User::find($user_id);
 
        $request->validate([
            'email' => 'required|email'
        ]);

        if($user->user_type === 'super_admin'){
           $user_type = 'admin';
        }elseif($user->user_type === 'admin'){
            $user_type = 'agency';
        }else{
            $user_type = 'agent';
        }
 
        // 🔎 Check invitation only if expired OR rejected
        $invitation = UserInvitations::where('email', $request->email)
            ->where('user_type', $user_type)
            ->whereIn('status', ['expired', 'rejected'])
            ->first();
 
        if (!$invitation) {
            return response()->json([
                'message' => 'Only expired or rejected invitations can be resent.'
            ], 422);
        }
 
        $Settings = Settings::where('key','expired_link_duration')->first();
        $expireDays = (int)$Settings->value ?? 1;
 
        // ✅ Regenerate token & activate again
        $invitation->update([
            'token'      => Str::random(64),
            'status'     => 'pending',
            'expires_at' => now()->addDays($expireDays),
        ]);
 
        $payload = [
            'token' => $invitation->token,
            'email' => $invitation->email,
            'name'  => $invitation->first_name.'-'.$invitation->last_name,
        ];
 
        $encrypted = Crypt::encryptString(json_encode($payload));
 
        $frontendUrl = config('app.frontend_url')
            . 'accept-invitation?data=' . urlencode($encrypted);
 
        Mail::to($invitation->email)
            ->send(new \App\Mail\UserInvitationMail($invitation, $user, $frontendUrl));

        Cache::tags(['invitation_list_user_' . $user->id])->flush();
 
        return response()->json([
            'message' => 'Invitation resent successfully.'
        ]);
    }
 
    public function accept(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'password' => 'required|confirmed'
        ]);
 
        try {
            $decoded = urldecode($request->data);
 
            $decrypted = json_decode(
                \Crypt::decryptString($decoded),
                true
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or corrupted invitation link.'
            ], 400);
        }
 
        $invitation = UserInvitations::where('token', $decrypted['token'])
            ->where('status', 'pending')
            ->first();

        Cache::tags(['roles_list'])->flush();
        Cache::tags(['permissions_list'])->flush();
 
        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found or already used.'
            ], 404);
        }
 
        if ($invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
             return response()->json([
                'message' => 'Invitation expired.'
            ], 403);
        }
 
        /* ================= ADMIN ================= */
        if ($invitation->user_type === 'admin') {
            $user = User::create([
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'user_type' => 'admin',
                'email_verified_at' => now(),
            ]);

            $role = Role::find($invitation->role_id);

             if (!$role) {
                return response()->json([
                    'message' => 'Role not found for this invitation.'
                ], 404);
            }

            $user->assignRole($role);
        }

        /* ================= AGENCY ================= */
        elseif ($invitation->user_type === 'agency') {

            $tenant = Tenant::create([
                'id' => Str::uuid()->toString(),
                'agency_name' => $invitation->first_name.' '.$invitation->last_name,
                'database' => 'tenant' . Str::random(8),
            ]);

            Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id]
            ]);

            $user = User::create([
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'user_type' => 'agency',
                'email_verified_at' => now(),
                'tenant_id' => $tenant->id,
            ]);

            $role = Role::find($invitation->role_id);

            if (!$role) {
                return response()->json([
                    'message' => 'Role not found for this invitation.'
                ], 404);
            }

            $user->assignRole($role);

            tenancy()->initialize($tenant);
            $allPermissions = collect([
                'user-access',
                'user-create',
                'user-edit',
                'user-show',
                'user-delete',  
            ])->map(function ($permission) {
                return Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'sanctum'
                ]);
            });

            tenancy()->end();
            
            Cache::tags(['agency_list'])->flush();
        }

        /* ================= AGENT ================= */
        elseif ($invitation->user_type === 'agent') {

            $tenant = Tenant::find($invitation->tenant_id);

            if (!$tenant) {
                return;
            }

            CentralTenantTelations::create([
                'tenant_id' => $tenant->id,
                'email' => $invitation->email,
                'status' => 'active',
            ]);

            tenancy()->initialize($tenant);

            $user = User::create([
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'email' => $invitation->email,
                'email_verified_at' => now(),
                'password' => Hash::make($request->password),
            ]);

            $role = Role::find($invitation->role_id);

             if (!$role) {
                return response()->json([
                    'message' => 'Role not found for this invitation.'
                ], 404);
            }

            $user->assignRole($role);

            tenancy()->end();
            Cache::tags(['agents_list'])->flush();
        }

        /* ================= INVALID ================= */
        else {
            return response()->json([
                'message' => 'Something went wrong.',
            ], 500);
        }

        $invitation->update([
            'status' => 'accepted'
        ]);
 
        Cache::tags(['users_list'])->flush();
        return response()->json([
            'message' => 'Account created successfully.'
        ]);
    }
 
    private function getPermissionsForRole(string $roleName)
    {
        $map = [
            'super_admin' => ['%'], // all permissions
            'admin' => ['admin', 'agency', 'agent'],
            'agency' => ['agency', 'agent'],
            'agent' => ['agent'],
        ];
 
        if ($roleName === 'main_super_admin') {
            return Permission::pluck('name')->toArray();
        }
 
        $groups = $map[$roleName] ?? [];
 
        return Permission::where(function ($q) use ($groups) {
            foreach ($groups as $group) {
                $q->orWhere('name', 'like', $group . '-%');
            }
        })->pluck('name')->toArray();
    }

    public function invitationList(Request $request): JsonResponse
    {
        $user = $request->user();
        // $user = User::find(1);

        Cache::tags(['invitation_list_user_' . $user->id])->flush();
        
        // Create unique cache key based on URL + user
        $cacheKey = 'invitation_list_user_' . $user->id . '_' . md5($request->fullUrl());

        $invitations = Cache::tags(['invitation_list_user_' . $user->id])->remember($cacheKey, 300, function () use ($request, $user) {
 
            $limit = $request->limit ?? 10;
            $sort  = $request->sort ?? 'created_at';
            $dir   = $request->dir ?? 'desc';

            $query = UserInvitations::select('id', 'first_name', 'last_name', 'email', 'user_type', 'status', 'created_at')
                ->where('created_by', $user->id);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // $query->where('user_type', 'admin');

            if ($request->user_type != null) {
                $query->where('user_type', $request->user_type);
            }

            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            return $query->orderBy($sort, $dir)->paginate($limit);
        });

        return response()->json([
            'message' => 'Invitation list retrieved successfully',
            'status' => true,
            'data' => $invitations
        ]);
    }

    public function invitationDetails(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:user_invitations,id',
        ]);

        $user = $request->user();

        $invitation = UserInvitations::where('id', $request->id)
            ->where('created_by', $user->id)
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invitation not found.',
                'status' => false,
            ], 404);
        }

        return response()->json([
            'data' => $invitation
        ]);
    }
}