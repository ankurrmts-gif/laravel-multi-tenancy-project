<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MakeModuleWithPermission extends Command
{
    protected $signature = 'module:create {name}';
    protected $description = 'Create module with permissions';

    public function handle()
    {
        $moduleName = $this->argument('name');

        // Create Module
        \Artisan::call('module:make', [
            'name' => [$moduleName]
        ]);

        exec('composer dump-autoload');

        $moduleSlug = Str::slug($moduleName);

        $permissionActions = [
            'access',
            'add',
            'create',
            'edit',
            'update',
            'delete',
            'view',
        ];

        $permissions = collect($permissionActions)->map(function ($action) use ($moduleSlug) {
            return $moduleSlug . '_' . $action;
        })->toArray();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }

        // Assign to Super Admin role (create if missing)
        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'sanctum',
        ]);

        $superAdmin->givePermissionTo($permissions);

        $this->info('Module created with permissions successfully.');
    }
}