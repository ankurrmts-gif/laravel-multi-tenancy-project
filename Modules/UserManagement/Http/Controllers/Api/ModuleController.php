<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Module;
use App\Models\ModuleField;
use Illuminate\Support\Str;
use App\Services\DynamicTableService;

class ModuleController extends Controller
{
    // CREATE MODULE
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $module = Module::create([
                'model_name' => $request->model_name,
                'slug' => Str::slug($request->model_name),
                'menu_title' => $request->menu_title,
                'parent_menu' => $request->parent_menu,
                'status' => $request->status,
                'icon' => $request->icon,
                'user_type' => $request->user_type,
                'order_number' => $request->order_number,
                'tenant_id' => $request->tenant_id,
                'actions' => $request->actions,
            ]);

            foreach ($request->fields as $field) {
                ModuleField::create([
                    'module_id' => $module->id,
                    'label' => $field['label'],
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'required' => $field['required'] ?? false,
                    'is_unique' => $field['is_unique'] ?? false,
                    'options' => $field['options'] ?? null,
                ]);
            }

            DynamicTableService::createTable(
                $module->slug,
                $request->fields
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Module Created',
                'data' => $module
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // LIST
    public function index()
    {
        return response()->json(Module::with('fields')->get());
    }

    // SHOW
    public function show($id)
    {
        return response()->json(
            Module::with('fields')->findOrFail($id)
        );
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $module->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Updated'
        ]);
    }

    // DELETE
    public function destroy($id)
    {
        Module::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }
}