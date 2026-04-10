<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;
 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\SmtpSetting;
use App\Support\TenantMailer;
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
use App\Models\Settings,App\Models\ContactUs;
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
use App\Models\DatatableSetting;
 
class CommonController extends Controller
{  
    public function getGlobalSettings(Request $request): JsonResponse
    {
        $settings = Settings::select('key', 'value')->where('type', 'file')->get();

        return response()->json([
            'status' => true,
            'data' => $settings
        ]);
    }

    public function getAllSettings(Request $request): JsonResponse
    {
       // if (!$request->user()->can('settings-access')) {
        //     return response()->json(['message' => 'Access Denied.'], 403);
        // }
        $authUser = $request->user();

        tenancy()->end();

        if($authUser->user_type == 'tenant'){
            tenancy()->initialize($authUser->tenant_id);
        }
        $settings = Settings::all()->groupBy('group');

        $response = [];
        foreach ($settings as $group => $items) {
            foreach ($items as $item) {

                // JSON decode
                $value = json_decode($item->value, true);
                $value = $value ?? $item->value;

                $response[$group][$item->key] = [
                    'value' => $value,
                    'type'  => $item->type ?? 'text' // default text
                ];
            }
        }
        tenancy()->end();

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
    }

    public function getAllFormSettings(Request $request): JsonResponse{
         $authUser = $request->user();

        tenancy()->end();

        if($authUser->user_type == 'agency'){
            tenancy()->initialize($authUser->tenant_id);
        }
        $settings = Settings::all()->groupBy('group');

        $response = [];
        foreach ($settings as $group => $items) {
            foreach ($items as $item) {

                // JSON decode
                $value = json_decode($item->value, true);
                $value = $value ?? $item->value;

                $response[$group][$item->key] = [
                    'value' => $value,
                    'type'  => $item->type ?? 'text' // default text
                ];
            }
        }
        tenancy()->end();

        return response()->json([
            'status' => true,
            'data' => $response
        ]);
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
 
    // public function updateSettings(Request $request): JsonResponse
    // {
    //     if (!$request->user()->can('settings-edit')) {
    //         return response()->json(['message' => 'Access Denied.'], 403);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'settings' => 'required|array',
    //         'settings.*.id' => 'required|exists:settings,id',
    //         'settings.*.key' => 'required|string',
    //         'settings.*.value' => 'required'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     foreach ($request->settings as $index => $settingData) {
    //         $setting = Settings::find($settingData['id']);

    //         if (!$setting) {
    //             continue;
    //         }

    //         $key = $settingData['key'];
    //         $value = $settingData['value'];

    //         // 🔥 Handle Base64 Image Upload (logo & favicon)
    //         if (in_array($key, ['logo', 'favicon_icon', 'mini_logo', 'default_logo_dark', 'mini_logo_dark'])) {
    //             $base64 = $request->input("settings.$index.value");

    //             if ($base64) {
    //                 // ✅ Detect image type (including svg+xml)
    //                 if (preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,/', $base64, $type)) {

    //                     $image = substr($base64, strpos($base64, ',') + 1);
    //                     $image = base64_decode($image);

    //                     if ($image === false) {
    //                         throw new \Exception('Base64 decode failed');
    //                     }

    //                     // ✅ Fix extension for svg+xml
    //                     $extension = strtolower($type[1]);
    //                     if ($extension === 'svg+xml') {
    //                         $extension = 'svg';
    //                     }

    //                     // 👉 Allowed extensions
    //                     if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'svg'])) {
    //                         throw new \Exception('Invalid image type');
    //                     }

    //                     // 👉 Folder path
    //                     $folder = public_path('uploads/settings');

    //                     if (!file_exists($folder)) {
    //                         mkdir($folder, 0755, true);
    //                     }

    //                     // 👉 Old image delete
    //                     if ($setting->value && file_exists(public_path($setting->value))) {
    //                         unlink(public_path($setting->value));
    //                     }

    //                     // 👉 File name generate
    //                     $fileName = time() . '_' . uniqid() . '.' . $extension;

    //                     // 👉 Save image
    //                     file_put_contents($folder . '/' . $fileName, $image);

    //                     // 👉 Save path in DB
    //                     $value = 'uploads/settings/' . $fileName;
    //                 }
    //             }
    //         }

    //         $setting->update([
    //             'key' => $key,
    //             'value' => $value
    //         ]);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Settings updated successfully!'
    //     ], 200);
    // }

    public function updateSettings(Request $request)
    {
        $data = $request->all();

        $authUser = $request->user();
        tenancy()->end();

        if($authUser->user_type == 'agency'){
            tenancy()->initialize($authUser->tenant_id);
            foreach ($data as $group => $settings) {

                if (!is_array($settings)) continue;

                foreach ($settings as $key => $setting) {

                    // 🧠 Handle both formats (old + new)
                    if (is_array($setting) && isset($setting['value'])) {
                        $value = $setting['value'];
                        $type  = $setting['type'] ?? 'text';
                    } else {
                        $value = $setting;
                        $existing = Settings::where('key', $key)->first();
                        $type = $existing->type ?? 'text';
                    }

                    $existing = Settings::where('key', $key)->first();

                    /**
                     * 🔥 BASE64 IMAGE
                     */
                    if (!empty($value) && is_string($value) &&
                        preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,/', $value, $typeMatch)
                    ) {

                        $image = base64_decode(substr($value, strpos($value, ',') + 1));

                        if ($image === false) {
                            return response()->json(['status'=>false,'message'=>'Base64 decode failed'],400);
                        }

                        $extension = strtolower($typeMatch[1]);
                        if ($extension === 'svg+xml') $extension = 'svg';

                        $folder = public_path('uploads/settings/tenant_' . $authUser->tenant_id);
                        if (!file_exists($folder)) mkdir($folder,0755,true);

                        // delete old
                        if ($existing && $existing->value && file_exists(public_path($existing->value))) {
                            unlink(public_path($existing->value));
                        }

                        $fileName = time().'_'.uniqid().'.'.$extension;
                        file_put_contents($folder.'/'.$fileName, $image);

                        $value = 'uploads/settings/tenant_' . $authUser->tenant_id . '/' . $fileName;
                        $type = 'file';
                    }

                    /**
                     * 🔥 MULTIPART FILE
                     */
                   elseif ($request->hasFile("$group.$key")) {
                        $path = 'settings/tenant_'.$authUser->tenant_id;

                        $value = $request
                            ->file("$group.$key")
                            ->store($path, 'public');

                        $type = 'file';
                    }

                    /**
                     * 🔥 AUTO TYPE FALLBACK (optional)
                     */
                    else {
                        if ($type === 'toggle') {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        }

                        if ($type === 'json' && is_array($value)) {
                            $value = json_encode($value);
                        }
                    }

                    Settings::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'type' => $type,
                            'group' => $group
                        ]
                    );
                }
            }
             tenancy()->end();
        }else{
            tenancy()->end();
            foreach ($data as $group => $settings) {

                if (!is_array($settings)) continue;

                foreach ($settings as $key => $setting) {

                    // 🧠 Handle both formats (old + new)
                    if (is_array($setting) && isset($setting['value'])) {
                        $value = $setting['value'];
                        $type  = $setting['type'] ?? 'text';
                    } else {
                        $value = $setting;
                        $existing = Settings::where('key', $key)->first();
                        $type = $existing->type ?? 'text';
                    }

                    $existing = Settings::where('key', $key)->first();

                    /**
                     * 🔥 BASE64 IMAGE
                     */
                    if (!empty($value) && is_string($value) &&
                        preg_match('/^data:image\/([a-zA-Z0-9\+\-\.]+);base64,/', $value, $typeMatch)
                    ) {

                        $image = base64_decode(substr($value, strpos($value, ',') + 1));

                        if ($image === false) {
                            return response()->json(['status'=>false,'message'=>'Base64 decode failed'],400);
                        }

                        $extension = strtolower($typeMatch[1]);
                        if ($extension === 'svg+xml') $extension = 'svg';

                        $folder = public_path('uploads/settings');
                        if (!file_exists($folder)) mkdir($folder,0755,true);

                        // delete old
                        if ($existing && $existing->value && file_exists(public_path($existing->value))) {
                            unlink(public_path($existing->value));
                        }

                        $fileName = time().'_'.uniqid().'.'.$extension;
                        file_put_contents($folder.'/'.$fileName, $image);

                        $value = 'uploads/settings/'.$fileName;
                        $type = 'file';
                    }

                    /**
                     * 🔥 MULTIPART FILE
                     */
                    elseif ($request->hasFile("$group.$key")) {
                        $value = $request->file("$group.$key")->store('settings','public');
                        $type = 'file';
                    }

                    /**
                     * 🔥 AUTO TYPE FALLBACK (optional)
                     */
                    else {
                        if ($type === 'toggle') {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        }

                        if ($type === 'json' && is_array($value)) {
                            $value = json_encode($value);
                        }
                    }

                    Settings::updateOrCreate(
                        ['key' => $key],
                        [
                            'value' => $value,
                            'type' => $type,
                            'group' => $group
                        ]
                    );
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully'
        ]);
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

        $contact = new ContactUs();
        $contact->name = $request->name;
        $contact->email = $request->email;
        $contact->message = $request->message;
        $contact->save();

        $EmailId = Settings::where('key', 'support_email')->first();

        $user = User::where('user_type', 'super_admin')->first();

        TenantMailer::apply($user);
        Mail::to($EmailId->value)
            ->send(new \App\Mail\ContactUsMail([
                'name' => $request->name,
                'email' => $request->email,
                'message' => $request->message,
            ]));

        return response()->json([
            'status' => true,
            'message' => 'Your message has been sent. We will get back to you shortly.'
        ], 200);
    }

    public function getSmtpSettings(Request $request)
    {   
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('config:cache');

        $authUser = $request->user();

        if($authUser->user_type == 'agency'){
            tenancy()->initialize($authUser->tenant_id);
        }

        $SmtpSetting = SmtpSetting::first();

        tenancy()->end();

        return response()->json([
            'status' => true,
            'data' => $SmtpSetting
        ]);
    }

    public function updateSmtp(Request $request)
    {
        $request->validate([
            'mailer'       => 'required|string',
            'host'         => 'required|string',
            'port'         => 'required|numeric',
            'username'     => 'nullable|string',
            'password'     => 'nullable|string',
            'encryption'   => 'nullable|string',
            'from_address' => 'required|email',
            'from_name'    => 'required|string',
        ]);

        try {

            $authUser = $request->user();
            
            tenancy()->end();

            if($authUser->user_type == 'agency'){
                tenancy()->initialize($authUser->tenant_id);
            }

            $SmtpSetting = SmtpSetting::first();
            if (!$SmtpSetting) {
                $SmtpSetting = new SmtpSetting();
            }

            $SmtpSetting->mailer = $request->mailer;
            $SmtpSetting->host = $request->host;
            $SmtpSetting->port = $request->port;
            $SmtpSetting->username = $request->username;
            $SmtpSetting->password = $request->password;
            $SmtpSetting->encryption = $request->encryption;
            $SmtpSetting->from_address = $request->from_address;
            $SmtpSetting->from_name = $request->from_name;
            $SmtpSetting->save();

             // ✅ Clear cache
             Artisan::call('config:clear');
             Artisan::call('cache:clear');

            tenancy()->end();

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

    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');

             // 👉 AFTER SIZE
            $afterCache = $this->getFolderSize(storage_path('framework/cache'));
            $afterView  = $this->getFolderSize(storage_path('framework/views'));
            $afterLog   = $this->getFolderSize(storage_path('logs'));

            return response()->json([
                'status' => true,
                'message' => 'All caches cleared successfully',
                'data' => [
                    'after' => [
                        // 'cache' => $this->formatSize($afterCache),
                        // 'view'  => $this->formatSize($afterView),
                        // 'log'   => $this->formatSize($afterLog),
                        'total' => $this->formatSize($afterCache + $afterView + $afterLog),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getFolderSize($path){
        $size = 0;

        if (!file_exists($path)) {
            return 0;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getRole()
    {
        $roles = Role::where('type','agency')->get();

        return response()->json([
            'roles' => $roles
        ]);
    }

    public function clearRouteCache(): JsonResponse
    {
        Artisan::call('route:clear');

        return response()->json([
            'status' => true,
            'message' => 'Route cache cleared'
        ]);
    }

    public function clearConfigCache(): JsonResponse
    {
        Artisan::call('config:clear');

        return response()->json([
            'status' => true,
            'message' => 'Config cache cleared'
        ]);
    }

    public function clearAppCache(): JsonResponse
    {
        Artisan::call('cache:clear');

        return response()->json([
            'status' => true,
            'message' => 'Application cache cleared'
        ]);
    }

    public function clearViewCache(): JsonResponse
    {
        Artisan::call('view:clear');

        return response()->json([
            'status' => true,
            'message' => 'View cache cleared'
        ]);
    }

    public function clearLogs(): JsonResponse
    {
        try {
            $logPath = storage_path('logs/laravel.log');

            if (file_exists($logPath)) {
                file_put_contents($logPath, '');
            }

            return response()->json([
                'status' => true,
                'message' => 'Logs cleared'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

     // ✅ Get Settings
    public function getSettings(Request $request, $table)
    {
        $setting = DatatableSetting::where('table_key', $table)->first();

        if ($setting && !empty($setting->settings)) {

            $settings = is_array($setting->settings)
                ? $setting->settings
                : json_decode($setting->settings, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($settings)) {
                return response()->json([
                    'module_type' => $setting->module_type,
                    'module_id'   => $setting->module_id,
                    'settings'    => $settings
                ]);
            }
        }

        return response()->json([
            'module_type' => null,
            'module_id'   => null,
            'settings'    => $this->defaultSettings()
        ]);
    }

    // ✅ Save Settings
    public function saveSettings(Request $request, $table)
    {
        $validated = $request->validate([
            'module_type'        => 'required|string',
            'module_id'          => 'nullable|integer',

            'sort.field'        => 'required|string',
            'sort.order'        => 'required|in:asc,desc',

            'columns'           => 'required|array',
            'columns.*.key'     => 'required|string',
            'columns.*.label'   => 'required|string',
            'columns.*.visible' => 'required|boolean',

            'export'            => 'required|array',
            'export.csv'        => 'required|boolean',
            'export.excel'      => 'required|boolean',
            'export.pdf'        => 'required|boolean',

            'pagination'        => 'required|integer',
            'search_status'     => 'required|boolean',
            'filter_status'     => 'required|boolean',
        ]);

        DatatableSetting::updateOrCreate(
            [
                'table_key' => $table,
                'module_type' => $validated['module_type'],
                'module_id'   => $validated['module_id']
            ],
            [
                'settings' => json_encode($validated)
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Datatable settings saved successfully'
        ]);
    }

    // ✅ Datatable Data
    public function getData(Request $request, $table)
    {
        $query = $this->getQuery($table);

        // 🔍 Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                foreach ($request->searchable_columns ?? [] as $col) {
                    $q->orWhere($col, 'like', "%{$request->search}%");
                }
            });
        }

        // 🎯 Filters
        if ($request->filters) {
            foreach ($request->filters as $key => $value) {
                if ($value !== null) {
                    $query->where($key, $value);
                }
            }
        }

        // 🔃 Sorting
        if ($request->sort_field) {
            $query->orderBy($request->sort_field, $request->sort_order ?? 'asc');
        }

        return response()->json(
            $query->paginate($request->per_page ?? 10)
        );
    }

    // 🧠 Dynamic Query Resolver
    private function getQuery($table)
    {
        return match ($table) {
            'users' => User::query(),
            'products' => Product::query(),
            default => $this->dynamicModuleQuery($table),
        };
    }

    private function dynamicModuleQuery($table)
    {
        return DB::table($table); // dynamic table
    }

    // 🧩 Default Settings
    private function defaultSettings()
    {
        return [
            "sort" => [
                "field" => "id",
                "order" => "desc"
            ],
            "columns" => [
                [
                    "key" => "id",
                    "label" => "ID",
                    "visible" => true
                ], [
                    "key" => "created_at",
                    "label" => "Created At",
                    "visible" => true
                ], [
                    "key" => "action",
                    "label" => "Action",
                    "visible" => true
                ]
            ],
            "export" => [
                "csv" => false,
                "excel" => false,
                "pdf" => false
            ],
            "pagination" => 10,
            "search_status" => true,
            "filter_status" => false
        ];
    }
}