<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;
 
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
use App\Models\Tenant;
use App\Models\CentralTenantTelations;
use App\Models\UserInvitations;
use App\Models\User;
use App\Models\Settings;
use App\Models\ColumnTypes;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
 
class CommonController extends Controller
{  
    public function getAllSettings(Request $request): JsonResponse
    {
        if (!$request->user()->can('settings-access')) {
            return response()->json(['message' => 'Access Denied.'], 403);
        }
 
        $settings = Settings::select('id', 'key', 'value')->get();
 
        // if ($request->filled('search')) {
        //     $search = $request->search;
 
        //     $settings = $settings->where(function ($q) use ($search) {
        //         $q->where('key', 'like', "%{$search}%")
        //         ->orWhere('value', 'like', "%{$search}%");
        //     });
        // }

        // $settings = $settings->paginate(10);
        
        return response()->json([
            'settings' => $settings
        ],200);
    }
 
    public function getSettingDetails(Request $request): JsonResponse
    {
        if (!$request->user()->can('settings-show')) {
            return response()->json(['message' => 'Access Denied.'], 403);
        }
 
        $request->validate([
            'id' => 'required'
        ]);
       
        $setting = Settings::find($request->id);
 
        if (!$setting) {
            return response()->json([
                'status' => false,
                'message' => 'Setting not found.'
            ], 404);
        }
 
        return response()->json([
            'setting' => $setting
        ],200);
    }
 
    public function addSettings(Request $request): JsonResponse
    {
        if (!$request->user()->can('settings-create')) {
            return response()->json(['message' => 'Access Denied.'], 403);
        }
 
        $request->validate([
            'key' => 'required|unique:settings,key',
            'value' => 'required'
        ]);
 
        $Settings = new Settings;
        $Settings->key = $request->key;
        $Settings->value = $request->value;
        $Settings->save();
 
        return response()->json([
            'status' => true,
            'message' => 'Setting Added!'
        ],200);
    }
 
    public function updateSettings(Request $request): JsonResponse
    {
        if (!$request->user()->can('settings-edit')) {
            return response()->json(['message' => 'Access Denied.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.id' => 'required|exists:settings,id',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->settings as $index => $settingData) {
            $setting = Settings::find($settingData['id']);

            if (!$setting) {
                continue;
            }

            $key = $settingData['key'];
            $value = $settingData['value'];

            // 🔥 Handle Image Upload
            if (in_array($key, ['logo', 'favicon_icon'])) {
                if ($request->hasFile("settings.$index.value")) {
                    $file = $request->file("settings.$index.value");

                    $folder = public_path('uploads/settings');

                    // 👉 Folder create if not exists
                    if (!file_exists($folder)) {
                        mkdir($folder, 0755, true);
                    }

                    // 👉 Old image delete
                    if ($setting->value && file_exists(public_path($setting->value))) {
                        unlink(public_path($setting->value));
                    }

                    // 👉 New file name
                    $fileName = time() . '_' . $file->getClientOriginalName();

                    // 👉 Move file to public folder
                    $file->move($folder, $fileName);

                    // 👉 Save path in DB
                    $value = 'uploads/settings/' . $fileName;
                }
            }

            $setting->update([
                'key' => $key,
                'value' => $value
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully!'
        ], 200);
    }
 
    public function deshboardCount(Request $request): JsonResponse
    {   
        $authUser = $request->user();

        $relation = CentralTenantTelations::on('mysql')->where('email', $authUser->email)->first();

        /*
        |--------------------------------------------------------------------------
        | Tenant User
        |--------------------------------------------------------------------------
        */
        if ($relation) {
            return response()->json([
                'status' => 'tenant'
            ]);
        }

        $totalUsers = User::count();
        $Admins = User::where('user_type','admin')->count();
        $Aegency = User::where('user_type','agency')->count();
        
        if($authUser->user_type == 'agency') {
            $Agents = CentralTenantTelations::where('tenant_id', $authUser->tenant_id)->count();
        } else {
            $Agents = CentralTenantTelations::count();
        }
        
        $totalInvitation = UserInvitations::count();
        $totalPendingInvitation = UserInvitations::where('status','pending')->count();
        $totalAcceptedInvitation = UserInvitations::where('status','accepted')->count();
        $totalRejectedInvitation = UserInvitations::where('status','rejected')->count();
        $totalExpiredInvitation = UserInvitations::where('status','expired')->count();
        $totalAdminInvitation = UserInvitations::where('user_type','admin')->count();
        $totalAgencyInvitation = UserInvitations::where('user_type','agency')->count();
        $totalAgentInvitation = UserInvitations::where('user_type','agent')->count();

        return response()->json([
            'total_users' => $totalUsers + $Agents,
            'total_admin' => $Admins,
            'total_agency' => $Aegency,
            'total_agents' => $Agents,
            'total_invitation' => $totalInvitation,
            'total_pending_invitation' => $totalPendingInvitation,
            'total_accepted_invitation' => $totalAcceptedInvitation,
            'total_rejected_invitation' => $totalRejectedInvitation,
            'total_expired_invitation' => $totalExpiredInvitation,
            'total_admin_invitation' => $totalAdminInvitation,
            'total_agency_invitation' => $totalAgencyInvitation,
            'total_agent_invitation' => $totalAgentInvitation,
        ], 200);
    }

    public function getColumnTypes(): JsonResponse
    {
        try {
            $types = ColumnTypes::select(
                'id',
                'name',
                'input_type',
                'db_type',
                'has_options'
            )
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Column types fetched successfully',
                'data' => $types
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllModels(): JsonResponse
    {
        try {
            $models = collect(File::files(app_path('Models')))
                ->map(function ($file) {
                    return pathinfo($file, PATHINFO_FILENAME);
                });

            return response()->json([
                'success' => true,
                'message' => 'Models fetched successfully',
                'data' => $models
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllModelFields($model_name): JsonResponse
    {
        try {
            $modelClass = 'App\\Models\\' . $model_name;

            if (!class_exists($modelClass)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $modelInstance = new $modelClass;
            $tableName = $modelInstance->getTable();
            $fields = Schema::getColumnListing($tableName);

            return response()->json([
                'success' => true,
                'message' => 'Model fields fetched successfully',
                'data' => $fields
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}