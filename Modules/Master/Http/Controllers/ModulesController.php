<?php
namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleFieldOption;
use App\Models\ModulePermission;
use App\Models\Role;
use App\Models\User;
use App\Services\ModuleFileStructureService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    public function index()
    {
        $user = auth()->user();

        $modules = Module::where('status', true)
            ->orderBy('order_number')
            ->get();

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
            'module.model_name'        => 'required|string',
            'module.slug'              => 'required|string|unique:modules,slug',
            'module.menu_title'        => 'required|string',
            'module.parent_menu'       => 'nullable|integer',
            'module.status'            => 'boolean',
            'module.icon'              => 'nullable|string',
            'module.user_type'         => 'required|string',
            'module.order_number'      => 'integer',
            'module.tenant_id'         => 'nullable|string',
            'module.actions'           => 'nullable|array',
            'module.created_by'        => 'required|integer',
            'module.assigned_admins'   => 'array',
            'module.assigned_agencies' => 'array',
            'module.permissions'       => 'array',
            'fields'                   => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moduleData = $request->input('module');

        $resolvedTenantId = $this->resolveTenantId($request, $moduleData);
        if ($resolvedTenantId !== null) {
            $moduleData['tenant_id'] = $resolvedTenantId;
        }

        $module = Module::create($moduleData);

        // Assigned admins
        if (isset($moduleData['assigned_admins'])) {
            $admins = collect($moduleData['assigned_admins'])->pluck('id')->toArray();
            $module->assignedAdmins()->attach($admins);
        }

        // Assigned agencies
        if (isset($moduleData['assigned_agencies'])) {
            $agencies = collect($moduleData['assigned_agencies'])->pluck('id')->toArray();
            $module->assignedAgencies()->attach($agencies);
        }

        // Permissions
        $allPermissions = [];
        if (isset($moduleData['permissions'])) {
            $permissionActions = [
                1 => 'access',
                2 => 'add',
                3 => 'create',
                4 => 'edit',
                5 => 'update',
                6 => 'delete',
            ];

            foreach ($moduleData['permissions'] as $permId) {
                $action         = $permissionActions[$permId] ?? 'permission_' . $permId;
                $permissionName = $module->slug . '_' . $action;

                ModulePermission::create([
                    'module_id'       => $module->id,
                    'user_id'         => $moduleData['created_by'],
                    'permission_name' => $permissionName,
                ]);

                $allPermissions[] = $permissionName;
            }
        }

        // Assign permissions based on user_type
        if (! empty($allPermissions) && ! empty($moduleData['user_type'])) {
            $rolesToAssign = [];
            if (in_array($moduleData['user_type'], ['all', 'admin'])) {
                $rolesToAssign[] = 'admin';
            }
            if (in_array($moduleData['user_type'], ['all', 'agency'])) {
                $rolesToAssign[] = 'agency';
            }

            foreach ($rolesToAssign as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $role->givePermissionTo($allPermissions);
                }
            }
        }

        // Fields
        if ($request->has('fields')) {
            foreach ($request->input('fields') as $fieldData) {
                $field = ModuleField::create(array_merge($fieldData, ['module_id' => $module->id]));
                if (isset($fieldData['options'])) {
                    foreach ($fieldData['options'] as $option) {
                        ModuleFieldOption::create(array_merge($option, ['module_field_id' => $field->id]));
                    }
                }
            }
        }

        // Load fields with column type
        $module->load('fields.columnType');

        // Generate files and create directory structure
        $this->generateModuleFiles($module);
        $fileService = new ModuleFileStructureService();
        $fileService->createModuleDirectories($module->slug);
        $fileService->createGitkeepFiles($module->slug);

        return response()->json(['success' => true, 'message' => 'Module created successfully']);
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
            'module.id'         => 'required|integer',
            'module.model_name' => 'required|string',
            'module.slug'       => 'required|string|unique:modules,slug,' . $id,
            // similar to store
            'fields'            => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $moduleData       = $request->input('module');
            $resolvedTenantId = $this->resolveTenantId($request, $moduleData);
            if ($resolvedTenantId !== null) {
                $moduleData['tenant_id'] = $resolvedTenantId;
            }

            $module->update($moduleData);

            // Update assignments
            $module->assignedAdmins()->sync(collect($moduleData['assigned_admins'] ?? [])->pluck('id')->toArray());
            $module->assignedAgencies()->sync(collect($moduleData['assigned_agencies'] ?? [])->pluck('id')->toArray());

            // Update permissions - simplified
            $module->permissions()->delete();
            $allPermissions = [];
            if (isset($moduleData['permissions'])) {
                $permissionActions = [
                    1 => 'access',
                    2 => 'add',
                    3 => 'create',
                    4 => 'edit',
                    5 => 'update',
                    6 => 'delete',
                ];

                foreach ($moduleData['permissions'] as $permId) {
                    $action         = $permissionActions[$permId] ?? 'permission_' . $permId;
                    $permissionName = $module->slug . '_' . $action;
                    ModulePermission::create([
                        'module_id'       => $module->id,
                        'user_id'         => $moduleData['created_by'],
                        'permission_name' => $permissionName,
                    ]);
                    $allPermissions[] = $permissionName;
                }
            }

            // Assign permissions based on user_type
            if (! empty($allPermissions) && ! empty($moduleData['user_type'])) {
                $rolesToAssign = [];
                if (in_array($moduleData['user_type'], ['all', 'admin'])) {
                    $rolesToAssign[] = 'admin';
                }
                if (in_array($moduleData['user_type'], ['all', 'agency'])) {
                    $rolesToAssign[] = 'agency';
                }

                foreach ($rolesToAssign as $roleName) {
                    $role = Role::where('name', $roleName)->first();
                    if ($role) {
                        $role->syncPermissions($allPermissions);
                    }
                }
            }

                                         // Update fields - simplified, assume replace all
            $module->fields()->delete(); // This will cascade options
            if ($request->has('fields')) {
                foreach ($request->input('fields') as $fieldData) {
                    $field = ModuleField::create(array_merge($fieldData, ['module_id' => $module->id]));
                    if (isset($fieldData['options'])) {
                        foreach ($fieldData['options'] as $option) {
                            ModuleFieldOption::create(array_merge($option, ['module_field_id' => $field->id]));
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Module updated successfully']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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
    $modelName = $module->model_name;
    $table = strtolower(Str::plural($module->slug)); // projects
    $fk    = strtolower(Str::singular($module->slug)); // project
    $baseTime = now();

    $mainMigrations = [];
    $fileMigrations = [];
    $pivotMigrations = [];

    /*
    |----------------------------------------------------------------------
    | MAIN TABLE
    |----------------------------------------------------------------------
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
        if (!Schema::hasTable('{$table}')) {
            Schema::create('{$table}', function (Blueprint \$table) {
                \$table->id();
PHP;

    foreach ($module->fields as $field) {
        $type = $field->columnType->db_type;
        $inputType = $field->columnType->input_type;

        // BELONGS TO RELATION
        if (!$field->is_multiple && $field->model_name) {
            $relatedTable = strtolower(Str::plural(Str::singular($field->model_name)));
            $migrationContent .= <<<PHP

                \$table->unsignedBigInteger('{$field->db_column}')->nullable();
PHP;
            continue;
        }

        // NORMAL FIELD
        if (!in_array($inputType, ['file','photo'])) {
            $migrationContent .= <<<PHP

                \$table->{$type}('{$field->db_column}')->nullable();
PHP;
        }
    }

    $migrationContent .= <<<PHP

                \$table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;

    $mainPath = database_path("migrations/{$mainDate}_create_{$table}.php");
    File::put($mainPath, $migrationContent);
    $mainMigrations[] = "database/migrations/{$mainDate}_create_{$table}.php"; // relative path for Artisan

    /*
    |----------------------------------------------------------------------
    | FILE / PHOTO TABLES
    |----------------------------------------------------------------------
    */
    $i = 1;
    foreach ($module->fields as $field) {
        if (in_array($field->columnType->input_type, ['file','photo']) && $field->is_multiple) {
            $attachTable = "{$table}_{$field->db_column}";
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
        if (!Schema::hasTable('{$attachTable}')) {
            Schema::create('{$attachTable}', function (Blueprint \$table) {
                \$table->id();
                \$table->unsignedBigInteger('{$fk}_id');
                \$table->string('file_name');
                \$table->string('file_path');
                \$table->string('mime_type')->nullable();
                \$table->integer('file_size')->nullable();
                \$table->timestamps();
            });
        }

        // Add foreign key constraint separately to avoid dependency issues
        if (Schema::hasTable('{$table}') && !collect(DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = '{$attachTable}' AND COLUMN_NAME = '{$fk}_id' AND REFERENCED_TABLE_NAME = '{$table}'"))->count()) {
            Schema::table('{$attachTable}', function (Blueprint \$table) {
                \$table->foreign('{$fk}_id')->references('id')->on('{$table}')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Drop foreign key first
        if (Schema::hasTable('{$attachTable}')) {
            Schema::table('{$attachTable}', function (Blueprint \$table) {
                \$table->dropForeign(['{$fk}_id']);
            });
        }

        Schema::dropIfExists('{$attachTable}');
    }
};
PHP;

            $path = database_path("migrations/{$date}_create_{$attachTable}.php");
            File::put($path, $migration);
            $fileMigrations[] = "database/migrations/{$date}_create_{$attachTable}.php"; // relative path
        }
    }

    /*
    |----------------------------------------------------------------------
    | PIVOT TABLES (Many to Many)
    |----------------------------------------------------------------------
    */
    $createdPivots = [];
    foreach ($module->fields as $field) {
        if ($field->model_name && $field->is_multiple) {
            $relatedModel = Str::singular($field->model_name);
            $relatedTable = strtolower(Str::plural($relatedModel));
            $relatedFk = strtolower(Str::singular($relatedModel));

            $tables = [$table, $relatedTable];
            sort($tables);
            $pivot = implode('_', $tables); // feature_project

            if (in_array($pivot, $createdPivots)) {
                continue;
            }
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
        if (!Schema::hasTable('{$pivot}')) {
            Schema::create('{$pivot}', function (Blueprint \$table) {
                \$table->id();
                \$table->unsignedBigInteger('{$fk}_id');
                \$table->unsignedBigInteger('{$relatedFk}_id');
                \$table->timestamps();
            });
        }

        // Add foreign key constraints separately to avoid dependency issues
        if (Schema::hasTable('{$table}') && !collect(DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = '{$pivot}' AND COLUMN_NAME = '{$fk}_id' AND REFERENCED_TABLE_NAME = '{$table}'"))->count()) {
            Schema::table('{$pivot}', function (Blueprint \$table) {
                \$table->foreign('{$fk}_id')->references('id')->on('{$table}')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('{$relatedTable}') && !collect(DB::select("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = '{$pivot}' AND COLUMN_NAME = '{$relatedFk}_id' AND REFERENCED_TABLE_NAME = '{$relatedTable}'"))->count()) {
            Schema::table('{$pivot}', function (Blueprint \$table) {
                \$table->foreign('{$relatedFk}_id')->references('id')->on('{$relatedTable}')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Drop foreign keys first
        if (Schema::hasTable('{$pivot}')) {
            Schema::table('{$pivot}', function (Blueprint \$table) {
                \$table->dropForeign(['{$fk}_id']);
                \$table->dropForeign(['{$relatedFk}_id']);
            });
        }

        Schema::dropIfExists('{$pivot}');
    }
};
PHP;

            $path = database_path("migrations/{$date}_create_{$pivot}.php");
            File::put($path, $migration);
            $pivotMigrations[] = "database/migrations/{$date}_create_{$pivot}.php"; // relative path
        }
    }

    /*
    |----------------------------------------------------------------------
    | MODEL
    |----------------------------------------------------------------------
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
        if (!in_array($field->columnType->input_type, ['file','photo'])) {
            $modelContent .= "\n        '{$field->db_column}',";
        }
    }
    $modelContent .= "\n    ];\n";

    foreach ($module->fields as $field) {
        if ($field->model_name) {
            $relatedModel = Str::singular($field->model_name);
            $relatedTable = strtolower(Str::plural($relatedModel));
            $relatedFk = strtolower(Str::singular($relatedModel));

            if ($field->is_multiple) {
                $method = Str::plural(Str::camel($relatedFk));
                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

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
            } else {
                $method = Str::camel($relatedFk);
                $modelContent .= <<<PHP

    public function {$method}()
    {
        return \$this->belongsTo(
            \\App\\Models\\{$relatedModel}::class
        );
    }
PHP;
            }
        }
    }

    $modelContent .= "\n}\n";

    File::put(app_path("Models/{$modelName}.php"), $modelContent);

    /*
    |----------------------------------------------------------------------
    | RUN MIGRATIONS IN CORRECT ORDER
    |----------------------------------------------------------------------
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
