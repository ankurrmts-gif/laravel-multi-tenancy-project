<?php
 
namespace Database\Seeders;
 
use App\Models\User;
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
        //$this->call(ColumnTypesSeeder::class);

         /** -------------------------------------------------
         * ALWAYS USE CENTRAL DB
         * -------------------------------------------------*/
        config(['database.default' => 'mysql']);
 
        /** -------------------------------------------------
         * GET ALL PERMISSIONS FROM DB
         * (No static permissions now)
         * -------------------------------------------------*/
        // $allPermissions = Permission::where('guard_name', 'sanctum')->get();
 
        // if ($allPermissions->count() === 0) {
        //     $this->command->warn('⚠ No permissions found in DB. Seeder stopped.');
        //     return;
        // }
 
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
            // 'settings-create',
            'settings-edit',
            // 'settings-show',
            // 'settings-delete',
            'invitation-access',
            'admin-access',
            'admin-create',
            'admin-edit',
            'admin-show',
            'admin-delete',
            'agency-access',
            'agency-create',
            'agency-edit',
            'agency-show',
            'agency-delete',
            'agent-access',
            'agent-create',
            'agent-edit',
            'agent-show',
            'agent-delete',
            'module-access',
            'module-create',
            'module-edit',
            'module-show',
            'module-delete',
            'tenant-role-access',
            'tenant-role-create',
            'tenant-role-edit',
            'tenant-role-show',
            'tenant-role-delete',
            'tenant-permission-access',
            'tenant-permission-create',
            'tenant-permission-edit',
            'tenant-permission-show',
            'tenant-permission-delete',
            'tenant-module-access', 
            'tenant-module-create', 
            'tenant-module-edit', 
            'tenant-module-show', 
            'tenant-module-delete'
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
                'name' => 'Super Admin',
                'password' => Hash::make('Pa$$w0rd!'),
                'email_verified_at' => now(),
                'user_type' => 'super_admin',
            ]
        );
 
        Settings::insert([
            ['key' => 'expired_link_duration', 'value' => '2'],
            ['key' => 'support_email', 'value' => 'manushi.p.mts@gmail.com'],
            ['key' => 'access_token_expires_in_minutes', 'value' => '1'],
            ['key' => 'refresh_token_expires_in_minutes', 'value' => '120'],  
            ['key' => 'login_attempt_seconds', 'value' => '6'],   // gap in seconds
            ['key' => 'login_attempt_minute', 'value' => '5'],     // per minute
            ['key' => 'login_attempt_hour', 'value' => '30'],      // per hour
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
 
        $this->command->info('Super Admin created with ALL permissions');
    }
}
 