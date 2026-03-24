<?php

namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ModuleFieldOption;
use App\Models\ModulePermission;
use App\Models\User;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use DB;
use Illuminate\Support\Facades\File;
use Illuminate\Routing\Controller;

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

        $allowed = $modules->filter(fn ($module) => $this->userCanAccessModule($module, $user));

        $tree = [];
        $items = [];

        foreach ($allowed as $module) {
            $items[$module->id] = [
                'id' => $module->id,
                'menu_title' => $module->menu_title,
                'slug' => $module->slug,
                'icon' => $module->icon,
                'parent_menu' => $module->parent_menu,
                'children' => [],
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
        if (!$user) {
            return false;
        }

        // user_type restrictions
        if (!empty($module->user_type) && $module->user_type !== 'all' && $module->user_type !== $user->user_type) {
            return false;
        }

        $permissionName = $module->slug . '_access';

        // fallback: if no permission is defined for this module, allow it
        if (!$user->hasPermissionTo($permissionName, 'sanctum')) {
            return false;
        }

        return true;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module.model_name' => 'required|string',
            'module.slug' => 'required|string|unique:modules,slug',
            'module.menu_title' => 'required|string',
            'module.parent_menu' => 'nullable|integer',
            'module.status' => 'boolean',
            'module.icon' => 'nullable|string',
            'module.user_type' => 'required|string',
            'module.order_number' => 'integer',
            'module.tenant_id' => 'nullable|string',
            'module.actions' => 'nullable|array',
            'module.created_by' => 'required|integer',
            'module.assigned_admins' => 'array',
            'module.assigned_agencies' => 'array',
            'module.permissions' => 'array',
            'fields' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moduleData = $request->input('module');
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
                $action = $permissionActions[$permId] ?? 'permission_' . $permId;
                $permissionName = $module->slug . '_' . $action;

                ModulePermission::create([
                    'module_id' => $module->id,
                    'user_id' => $moduleData['created_by'],
                    'permission_name' => $permissionName,
                ]);

                $allPermissions[] = $permissionName;
            }
        }

        // Assign permissions based on user_type
        if (!empty($allPermissions) && !empty($moduleData['user_type'])) {
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

        // Generate files
        $this->generateModuleFiles($module);

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
            'module.id' => 'required|integer',
            'module.model_name' => 'required|string',
            'module.slug' => 'required|string|unique:modules,slug,' . $id,
            // similar to store
            'fields' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $moduleData = $request->input('module');
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
                    $action = $permissionActions[$permId] ?? 'permission_' . $permId;
                    $permissionName = $module->slug . '_' . $action;
                    ModulePermission::create([
                        'module_id' => $module->id,
                        'user_id' => $moduleData['created_by'],
                        'permission_name' => $permissionName,
                    ]);
                    $allPermissions[] = $permissionName;
                }
            }

            // Assign permissions based on user_type
            if (!empty($allPermissions) && !empty($moduleData['user_type'])) {
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
        $module = Module::findOrFail($id);
        $module->delete(); // Cascades
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
            'fields' => 'required|array',
            'fields.*.id' => 'required|integer',
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
            'module_id' => 'required|integer',
            'module_field_id' => 'required|integer',
            'column_type_id' => 'required|integer',
            'options' => 'required|array',
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
                'option_label' => $option['option_label'],
                'option_value' => $option['option_value'],
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Option updated successfully']);
    }

    private function generateModuleFiles($module)
    {
        $modelName = $module->model_name;
        $slug = $module->slug;
        $date = now()->format('Y_m_d_His');

        // Generate Migration
        $migrationContent = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$slug}', function (Blueprint \$table) {\n            \$table->id();\n";

        foreach ($module->fields as $field) {
            $fieldType = $field->columnType->db_type;
            $inputType = $field->columnType->input_type;

            if (in_array($inputType, ['file', 'photo'])) {
                if ($field->is_multiple) {
                    // have a separate attachments table for multiple uploads
                    $attachmentTable = strtolower($slug) . '_' . $field->db_column;
                    $attachDate = now()->addSecond()->format('Y_m_d_His');
                    $attachmentMigration = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$attachmentTable}', function (Blueprint \\$table) {\n            \\$table->id();\n            \\$table->unsignedBigInteger('{$slug}_id');\n            \\$table->string('file_name');\n            \\$table->string('file_path');\n            \\$table->string('mime_type')->nullable();\n            \\$table->integer('file_size')->nullable();\n            \\$table->timestamps();\n            \\$table->foreign('{$slug}_id')->references('id')->on('{$slug}')->onDelete('cascade');\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$attachmentTable}');\n    }\n};";
                    $attachmentPath = database_path("migrations/{$attachDate}_create_{$attachmentTable}_table.php");
                    File::put($attachmentPath, $attachmentMigration);
                    continue;
                }

                $fieldType = 'string';
            }

            if ($inputType === 'relation' && $field->model_name) {
                if ($field->is_multiple) {
                    continue;
                }
                $relatedTable = \Illuminate\Support\Str::plural(strtolower($field->model_name));
                $migrationContent .= "            \$table->unsignedBigInteger('{$field->db_column}')->nullable();\n";
                $migrationContent .= "            \$table->foreign('{$field->db_column}')->references('id')->on('{$relatedTable}')->onDelete('set null');\n";
                continue;
            }

            $migrationContent .= "            \$table->{$fieldType}('{$field->db_column}')->nullable();\n";
        }

        $migrationContent .= "            \$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$slug}');\n    }\n};";
        $migrationPath = database_path("migrations/{$date}_create_{$slug}_table.php");
        File::put($migrationPath, $migrationContent);

        // Create upload folder for file/photo fields in public
        foreach ($module->fields->whereIn('columnType.input_type', ['file', 'photo']) as $field) {
            $fieldDir = public_path("{$slug}/{$field->db_column}");
            if (!file_exists($fieldDir)) {
                mkdir($fieldDir, 0755, true);
            }
        }

        // Generate pivot table migrations for relation multi fields
        foreach ($module->fields as $field) {
            if ($field->columnType->input_type === 'relation' && $field->is_multiple && $field->model_name) {
                $relatedTable = \Illuminate\Support\Str::plural(strtolower($field->model_name));
                $pivotTable = strtolower($slug) . '_' . $relatedTable;
                $pivotDate = now()->addSecond()->format('Y_m_d_His');
                $pivotMigration = "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$pivotTable}', function (Blueprint \\$table) {\n            \\$table->id();\n            \\$table->unsignedBigInteger('{$slug}_id');\n            \\$table->unsignedBigInteger('{$field->model_name}_id');\n            \\$table->foreign('{$slug}_id')->references('id')->on('{$slug}')->onDelete('cascade');\n            \\$table->foreign('{$field->model_name}_id')->references('id')->on('{$relatedTable}')->onDelete('cascade');\n            \\$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$pivotTable}');\n    }\n};";
                $pivotPath = database_path("migrations/{$pivotDate}_create_{$pivotTable}_table.php");
                File::put($pivotPath, $pivotMigration);
            }
        }

        // Generate Model
        $modelContent = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$modelName} extends Model\n{\n    use HasFactory;\n\n    protected \$table = '{$slug}';\n\n    protected \$fillable = [\n";
        $fillables = [];
        foreach ($module->fields as $field) {
            $fillables[] = "        '{$field->db_column}'";
        }
        $modelContent .= implode(",\n", $fillables) . "\n    ];\n\n    protected \$casts = [\n";
        $casts = [];
        foreach ($module->fields as $field) {
            if ($field->columnType->db_type == 'boolean') {
                $casts[] = "        '{$field->db_column}' => 'boolean'";
            }
        }
        $modelContent .= implode(",\n", $casts) . "\n    ];\n}";
        $modelPath = app_path("Models/{$modelName}.php");
        File::put($modelPath, $modelContent);

        // Generate Controller
        $controllerContent = "<?php\n\nnamespace App\\Http\\Controllers;\n\nuse App\\Models\\{$modelName};\nuse Illuminate\\Http\\Request;\nuse Illuminate\\Support\\Facades\\Validator;\n\nclass {$modelName}Controller extends Controller\n{\n    public function index()\n    {\n        \$records = {$modelName}::all();\n        return response()->json(['success' => true, 'data' => \$records]);\n    }\n\n    public function store(Request \$request)\n    {\n        \$data = \$request->all();\n        \$record = {$modelName}::create(\$data);\n        return response()->json(['success' => true, 'message' => 'Record created successfully', 'data' => \$record]);\n    }\n\n    public function show(\$id)\n    {\n        \$record = {$modelName}::findOrFail(\$id);\n        return response()->json(['success' => true, 'data' => \$record]);\n    }\n\n    public function update(Request \$request, \$id)\n    {\n        \$record = {$modelName}::findOrFail(\$id);\n        \$record->update(\$request->all());\n        return response()->json(['success' => true, 'message' => 'Record updated successfully']);\n    }\n\n    public function destroy(\$id)\n    {\n        \$record = {$modelName}::findOrFail(\$id);\n        \$record->delete();\n        return response()->json(['success' => true, 'message' => 'Record deleted successfully']);\n    }\n}";
        $controllerPath = app_path("Http/Controllers/{$modelName}Controller.php");
        File::put($controllerPath, $controllerContent);

        // Generate route handling via module route definition (do not append to routes/api.php here)
        // Run Migration
        Artisan::call('migrate', ['--path' => "database/migrations/{$date}_create_{$slug}_table.php"]);
    }
}