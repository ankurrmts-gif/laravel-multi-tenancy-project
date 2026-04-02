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

            // 🔥 Handle Base64 Image Upload (logo & favicon)
            if (in_array($key, ['logo', 'favicon_icon', 'mini_logo', 'default_logo_dark', 'mini_logo_dark'])) {
                $base64 = $request->input("settings.$index.value");

                if ($base64) {
                    // ✅ Detect image type (including svg+xml)
                    if (preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,/', $base64, $type)) {

                        $image = substr($base64, strpos($base64, ',') + 1);
                        $image = base64_decode($image);

                        if ($image === false) {
                            throw new \Exception('Base64 decode failed');
                        }

                        // ✅ Fix extension for svg+xml
                        $extension = strtolower($type[1]);
                        if ($extension === 'svg+xml') {
                            $extension = 'svg';
                        }

                        // 👉 Allowed extensions
                        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'])) {
                            throw new \Exception('Invalid image type');
                        }

                        // 👉 Folder path
                        $folder = public_path('uploads/settings');

                        if (!file_exists($folder)) {
                            mkdir($folder, 0755, true);
                        }

                        // 👉 Old image delete
                        if ($setting->value && file_exists(public_path($setting->value))) {
                            unlink(public_path($setting->value));
                        }

                        // 👉 File name generate
                        $fileName = time() . '_' . uniqid() . '.' . $extension;

                        // 👉 Save image
                        file_put_contents($folder . '/' . $fileName, $image);

                        // 👉 Save path in DB
                        $value = 'uploads/settings/' . $fileName;
                    }
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

    public function contactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
        ]);
 
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $EmailId = Settings::where('key', 'support_email')->first();

        Mail::raw("Name: {$request->name}\nEmail: {$request->email}\nMessage: {$request->message}", function ($message) use ($request, $EmailId) {
            $message->to($EmailId->value)
                    ->subject('Contact Us Message');
        });

        return response()->json([
            'status' => true,
            'message' => 'Your message has been sent. We will get back to you shortly.'
        ], 200);
    }

    public function getSmtpSettings()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'MAIL_MAILER'       => env('MAIL_MAILER'),
                'MAIL_HOST'         => env('MAIL_HOST'),
                'MAIL_PORT'         => env('MAIL_PORT'),
                'MAIL_USERNAME'     => env('MAIL_USERNAME'),
                'MAIL_PASSWORD'     => env('MAIL_PASSWORD'),
                'MAIL_ENCRYPTION'   => env('MAIL_ENCRYPTION'),
                'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
                'MAIL_FROM_NAME'    => env('MAIL_FROM_NAME'),
            ]
        ]);
    }

    public function updateSmtp(Request $request)
    {
        $request->validate([
            'MAIL_MAILER'       => 'required|string',
            'MAIL_HOST'         => 'required|string',
            'MAIL_PORT'         => 'required|numeric',
            'MAIL_USERNAME'     => 'nullable|string',
            'MAIL_PASSWORD'     => 'nullable|string',
            'MAIL_ENCRYPTION'   => 'nullable|string',
            'MAIL_FROM_ADDRESS' => 'required|email',
            'MAIL_FROM_NAME'    => 'required|string',
        ]);

        try {
            $smtpValues = [
                'MAIL_MAILER'       => $request->MAIL_MAILER,
                'MAIL_HOST'         => $request->MAIL_HOST,
                'MAIL_PORT'         => $request->MAIL_PORT,
                'MAIL_USERNAME'     => $request->MAIL_USERNAME ?? '',
                'MAIL_PASSWORD'     => $request->MAIL_PASSWORD ?? '',
                'MAIL_ENCRYPTION'   => $request->MAIL_ENCRYPTION ?? '',
                'MAIL_FROM_ADDRESS' => $request->MAIL_FROM_ADDRESS,
                'MAIL_FROM_NAME'    => $request->MAIL_FROM_NAME,
            ];

            foreach ($smtpValues as $key => $value) {
                $this->setEnvValue($key, $value);
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            config([
                'mail.default' => $smtpValues['MAIL_MAILER'],
                'mail.mailers.smtp.transport' => $smtpValues['MAIL_MAILER'],
                'mail.mailers.smtp.host' => $smtpValues['MAIL_HOST'],
                'mail.mailers.smtp.port' => $smtpValues['MAIL_PORT'],
                'mail.mailers.smtp.username' => $smtpValues['MAIL_USERNAME'],
                'mail.mailers.smtp.password' => $smtpValues['MAIL_PASSWORD'],
                'mail.mailers.smtp.encryption' => $smtpValues['MAIL_ENCRYPTION'],
                'mail.from.address' => $smtpValues['MAIL_FROM_ADDRESS'],
                'mail.from.name' => $smtpValues['MAIL_FROM_NAME'],
            ]);

            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:cache');

            return response()->json([
                'status' => true,
                'message' => 'SMTP settings updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to update SMTP settings. ' . $e->getMessage()
            ], 500);
        }
    }

    protected function setEnvValue($key, $value)
    {
        $path = base_path('.env');

        if (!file_exists($path)) {
            throw new \RuntimeException('.env file not found');
        }

        $env = file_get_contents($path);
        $safeKey = preg_quote($key, '/');

        $value = $value === null ? '' : (string) $value;

        // Avoid TypeError in PHP 8+ (needle must be string) by using regex match for special chars.
        if (preg_match('/[\s"#]/', $value)) {
            $value = '"' . str_replace('"', '\\"', trim($value, '"')) . '"';
        }

        $newLine = "{$key}={$value}";

        if (preg_match("/^{$safeKey}=.*$/m", $env)) {
            $env = preg_replace("/^{$safeKey}=.*$/m", $newLine, $env);
        } else {
            $env = rtrim($env, "\n") . "\n" . $newLine;
        }

        file_put_contents($path, $env);
    }

    public function getRecaptchaSettings()
    {
        return response()->json([
            'status' => true,
            'data' => [
                'RECAPTCHA_SITE'   => config('services.recaptcha.site_key') ?? env('RECAPTCHA_SITE'),
                'RECAPTCHA_SECRET' => config('services.recaptcha.secret_key') ?? env('RECAPTCHA_SECRET'), // 🔐 hide secret
            ]
        ]);
    }

    public function updateRecaptchaSettings(Request $request)
    {
        $request->validate([
            'RECAPTCHA_SITE'   => 'required|string',
            'RECAPTCHA_SECRET' => 'nullable|string',
        ]);

        try {

            // ✅ Update .env
            $this->setEnvValueRecaptcha('RECAPTCHA_SITE', $request->RECAPTCHA_SITE);

            if ($request->filled('RECAPTCHA_SECRET')) {
                $this->setEnvValueRecaptcha('RECAPTCHA_SECRET', $request->RECAPTCHA_SECRET);
            }

            // ✅ Clear cache
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            return response()->json([
                'status' => true,
                'message' => 'reCAPTCHA settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected function setEnvValueRecaptcha($key, $value)
    {
        $path = base_path('.env');

        if (file_exists($path)) {

            $env = file_get_contents($path);

            $newLine = $key . '=' . $value;

            if (strpos($env, $key . '=') !== false) {
                $env = preg_replace("/^{$key}=.*/m", $newLine, $env);
            } else {
                $env .= "\n" . $newLine;
            }

            file_put_contents($path, $env);
        }
    }

}