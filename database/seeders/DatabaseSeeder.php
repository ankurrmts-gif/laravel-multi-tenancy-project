<?php
 
namespace Database\Seeders;
 
use App\Models\User,App\Models\SmtpSetting;
use App\Models\Settings;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\ColumnTypes;
 
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         /** -------------------------------------------------
         * ALWAYS USE CENTRAL DB
         * -------------------------------------------------*/
        config(['database.default' => 'mysql']);

        $this->call([
            EmailTemplateSeeder::class,
        ]);
 
        /** -------------------------------------------------
         * GET ALL PERMISSIONS FROM DB
         * (No static permissions now)
         * -------------------------------------------------*/
 
        $allPermissions = collect([
            'role-access',
            'role-create',
            'role-edit',
            'role-show',
            'role-delete',
            'permission-access',
            'permission-create',
            'permission-edit',
            'permission-show',
            'permission-delete',
            'settings-access',
            'settings-edit',
            'invitation-access',
            'master-module-access',
            'master-module-create',
            'master-module-edit',
            'master-module-show',
            'master-module-delete',
            'user-access',
            'user-edit',
            'user-show',
            'user-delete'
        ])->map(function ($permission) {
            return Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        });
 
        /** -------------------------------------------------
         * CREATE OR GET SUPER ADMIN ROLE
         * -------------------------------------------------*/
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'sanctum',
        ]);
 
        /** -------------------------------------------------
         * GIVE ALL PERMISSIONS TO SUPER ADMIN
         * -------------------------------------------------*/
        $superAdminRole->syncPermissions($allPermissions);
 
        /** -------------------------------------------------
         * CREATE OR GET SUPER ADMIN USER
         * -------------------------------------------------*/
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Pa$$w0rd!'),
                'email_verified_at' => now(),
                'user_type' => 'super_admin',
            ]
        );
 
        // Settings::insert([
        //     ['key' => 'expired_link_duration', 'value' => '2'],
        //     ['key' => 'support_email', 'value' => 'manushi.p.mts@gmail.com'],
        //     ['key' => 'access_token_expires_in_minutes', 'value' => '1'],
        //     ['key' => 'refresh_token_expires_in_minutes', 'value' => '120'],  
        //     ['key' => 'login_attempt_seconds', 'value' => '6'],   // gap in seconds
        //     ['key' => 'login_attempt_minute', 'value' => '5'],     // per minute
        //     ['key' => 'login_attempt_hour', 'value' => '30'],      // per hour
        //     ['key' => 'is_2fa_enabled', 'value' => '1'],
        //     ['key' => 'login_otp_expired_minutes', 'value' => '5'],
        //     ['key' => 'favicon_icon', 'value' => 'uploads/settings/1774937399_69cb65370c2a6.jpeg'],
        //     ['key' => 'mini_logo', 'value' => ''],
        //     ['key' => 'logo', 'value' => ''],
        //     ['key' => 'default_logo_dark', 'value' => ''],
        //     ['key' => 'mini_logo_dark', 'value' => ''],
        // ]);
 
        Settings::insert([
            // 🔐 AUTH SETTINGS
            [
                'key' => 'expired_link_duration_in_minutes',
                'value' => '2',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'access_token_expires_in_minutes',
                'value' => '5',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'refresh_token_expires_in_minutes',
                'value' => '120',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'login_attempt_seconds',
                'value' => '6',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'login_attempt_minute',
                'value' => '5',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'login_attempt_hour',
                'value' => '30',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'is_2fa_enabled',
                'value' => '0',
                'type' => 'toggle',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'login_otp_expired_minutes',
                'value' => '5',
                'type' => 'number',
                'group' => 'auth',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 🌐 GENERAL SETTINGS
            [
                'key' => 'site_name',
                'value' => 'Metronic',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_email',
                'value' => 'ankur.r.mts@gmail.com',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_phone',
                'value' => '+91 1234567890',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'footer_text',
                'value' => 'Keenthemes Inc.',
                'type' => 'input',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'primary_color',
                'value' => '#1234',
                'type' => 'color',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'secondary_color',
                'value' => '#5678',
                'type' => 'color',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_description',
                'value' => 'Lorem Ipsum is simply dummy text...',
                'type' => 'textarea',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 🖼️ FILES
            [
                'key' => 'favicon_icon',
                'value' => 'uploads/settings/1775466443_69d377cbbf230.jpeg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'logo',
                'value' => 'uploads/settings/1775027152_69ccc3d04ee52.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mini_logo',
                'value' => 'uploads/settings/1775027152_69ccc3d04db28.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_logo_dark',
                'value' => 'uploads/settings/1775027152_69ccc3d0504a1.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mini_logo_dark',
                'value' => 'uploads/settings/1775027152_69ccc3d051415.svg',
                'type' => 'file',
                'group' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 🔗 SOCIAL MEDIA
            [
                'key' => 'facebook',
                'value' => 'https://www.facebook.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'instagram',
                'value' => 'https://www.instagram.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'x',
                'value' => 'https://www.x.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'linkedin',
                'value' => 'https://www.linkedin.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'youtube',
                'value' => 'https://www.youtube.com',
                'type' => 'input',
                'group' => 'social_media',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 📍 CONTACT INFO
            [
                'key' => 'address',
                'value' => 'address',
                'type' => 'textarea',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'google_map_link',
                'value' => 'google_map_link',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'support_email',
                'value' => 'ankur.r.mts@gmail.com',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'support_phone',
                'value' => '+91 1234567890',
                'type' => 'input',
                'group' => 'contact_info',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // 📊 SEO
            [
                'key' => 'meta_title',
                'value' => 'meta_title',
                'type' => 'input',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_description',
                'value' => 'meta_description',
                'type' => 'textarea',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'meta_keywords',
                'type' => 'textarea',
                'group' => 'seo',
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);

        /** -------------------------------------------------
         * ASSIGN ROLE TO USER
         * -------------------------------------------------*/
        if (!$superAdmin->hasRole('Super Admin')) {
            $superAdmin->assignRole($superAdminRole);
        }

        // Seed column types
        ColumnTypes::insert([
            [
                'id' => 1,
                'name' => 'Text',
                'input_type' => 'text',
                'db_type' => 'string',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Email',
                'input_type' => 'text',
                'db_type' => 'string',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Textarea',
                'input_type' => 'textarea',
                'db_type' => 'text',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'Password',
                'input_type' => 'password',
                'db_type' => 'string',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'Radio',
                'input_type' => 'radio',
                'db_type' => 'string',
                'has_options' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'name' => 'Select',
                'input_type' => 'select',
                'db_type' => 'string',
                'has_options' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 7,
                'name' => 'Checkbox',
                'input_type' => 'checkbox',
                'db_type' => 'string',
                'has_options' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 8,
                'name' => 'Integer',
                'input_type' => 'number',
                'db_type' => 'integer',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9,
                'name' => 'Float',
                'input_type' => 'number',
                'db_type' => 'float',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 10,
                'name' => 'Money',
                'input_type' => 'number',
                'db_type' => 'decimal(10,2)',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'name' => 'Date Picker',
                'input_type' => 'date',
                'db_type' => 'date',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 12,
                'name' => 'Date/Time Picker',
                'input_type' => 'datetime-local',
                'db_type' => 'datetime',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 13,
                'name' => 'Time Picker',
                'input_type' => 'time',
                'db_type' => 'time',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 14,
                'name' => 'File',
                'input_type' => 'file',
                'db_type' => 'string',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 15,
                'name' => 'Photo',
                'input_type' => 'file',
                'db_type' => 'string',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 16,
                'name' => 'BelongsTo Relationship',
                'input_type' => 'select',
                'db_type' => 'unsignedBigInteger',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 17,
                'name' => 'BelongsToMany Relationship',
                'input_type' => 'select-multiple',
                'db_type' => 'json',
                'has_options' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        SmtpSetting::insert([
            'mailer' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'ankur.r.mts@gmail.com',
            'password' => 'ngbj xdyc rrkl rydf',
            'encryption' => 'SSL',
            'from_address' => 'ankur.r.mts@gmail.com',
            'from_name' => env('APP_NAME')
        ]);
 
        $this->command->info('Super Admin created with ALL permissions');
    }
}
 