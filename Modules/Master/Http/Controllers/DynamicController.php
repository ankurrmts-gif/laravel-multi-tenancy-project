<?php

namespace Modules\Master\Http\Controllers;

use App\Models\Module,App\Models\ModulePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ModuleField;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\ColumnTypes;
use Illuminate\Routing\Controller;

class DynamicController extends Controller
{
    /*
    |--------------------------------------------------
    | GET MODULE
    |--------------------------------------------------
    */
    private function getModule($slug)
    {
        tenancy()->end();
        return Module::where('slug', $slug)->firstOrFail();
    }

    /*
    |--------------------------------------------------
    | CHECK + CREATE FOLDER
    |--------------------------------------------------
    */
    private function ensureFolder($path)
    {
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }
    }

    /*
    |--------------------------------------------------
    | HANDLE SINGLE FILE
    |--------------------------------------------------
    */
    private function handleSingleFile($request, $table, $field)
    {
        $column = $field->db_column;

        // ✅ Normal upload
        if ($request->hasFile($column)) {
            return $request->file($column)->store($table, 'public');
        }

        // ✅ Base64 upload
        if ($request->has($column)) {

            $base64File = $request->$column;

            if (str_starts_with($base64File, 'data:')) {

                preg_match('/^data:(.*?);base64,/', $base64File, $matches);
                $mime = $matches[1] ?? 'image/jpeg';

                $fileData = preg_replace('/^data:.*;base64,/', '', $base64File);
                $fileData = base64_decode($fileData);

                $extension = explode('/', $mime)[1];
                $fileName = uniqid() . '.' . $extension;

                $folderPath = storage_path("app/public/{$table}");

                // 🔥 FIX: create directory if not exists
                if (!file_exists($folderPath)) {
                    mkdir($folderPath, 0777, true);
                }

                $filePath = "{$table}/{$fileName}";

                file_put_contents(storage_path("app/public/{$filePath}"), $fileData);

                return $filePath;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------
    | HANDLE MULTIPLE FILES
    |--------------------------------------------------
    */

    private function handleMultipleFiles($request, $table, $fk, $field, $recordId)
    {
        $column = $field->db_column;
        $filesData = [];

        /*
        |------------------------------------------
        | CASE 1: FILE UPLOAD (multipart)
        |------------------------------------------
        */
        if ($request->hasFile($column)) {

            foreach ($request->file($column) as $file) {

                if (!$file->isValid()) continue;

                $filePath = $file->store("{$table}/{$column}", 'public');

                $filesData[] = [
                    "{$fk}_id" => $recordId,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            return $filesData; // ✅ IMPORTANT: stop here
        }

        /*
        |------------------------------------------
        | CASE 2: BASE64
        |------------------------------------------
        */
        if ($request->has($column)) {

            $files = $request->input($column); // ✅ use input()

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $base64File) {

                // ✅ Ensure it's string before using str_starts_with
                if (!is_string($base64File)) continue;

                if (!str_starts_with($base64File, 'data:')) continue;

                preg_match('/^data:(.*?);base64,/', $base64File, $matches);
                $mime = $matches[1] ?? 'image/jpeg';

                $fileData = base64_decode(
                    preg_replace('/^data:.*;base64,/', '', $base64File)
                );

                if (!$fileData) continue;

                $extension = explode('/', $mime)[1] ?? 'jpg';
                $fileName = uniqid() . '.' . $extension;

                $filePath = "{$table}/{$column}/{$fileName}";

                Storage::disk('public')->put($filePath, $fileData);

                $filesData[] = [
                    "{$fk}_id" => $recordId,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'mime_type' => $mime,
                    'file_size' => strlen($fileData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        return $filesData;
    }

    /*
    |--------------------------------------------------
    | HANDLE PIVOT
    |--------------------------------------------------
    */
    private function handlePivot($request, $module, $recordId)
    {
        $table = Str::plural($module->slug);
        $fk = Str::singular($module->slug);

        foreach ($module->fields as $field) {

            if ($field->model_name && $field->is_multiple) {

                $relatedTable = strtolower(Str::plural($field->model_name));
                $relatedFk = strtolower(Str::singular($field->model_name));

                $tables = [$table, $relatedTable];
                sort($tables);

                $pivot = implode('_', $tables);

                if ($request->has($field->db_column)) {

                    DB::table($pivot)->where("{$fk}_id", $recordId)->delete();

                    foreach ($request->{$field->db_column} as $val) {
                        DB::table($pivot)->insert([
                            "{$fk}_id" => $recordId,
                            "{$relatedFk}_id" => $val,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------
    | INDEX
    |--------------------------------------------------
    */
    public function index(Request $request, $slug)
    {
        $user = auth()->user();

        // Get module
        $module = $this->getModule($slug);

        if($user->user_type != 'tenant'){
             tenancy()->end();
             // ✅ Step 1: Check module-specific permissions
           
            $modulePermissions = ModulePermission::where([
                'module_id' => $module->id,
                'user_id'   => $user->id
            ])->get();

                if($module->created_by == $user->id){
                        $permissionActions = [
                            1 => 'access',
                            2 => 'create',
                            3 => 'edit',
                            4 => 'show',
                            5 => 'delete',
                        ];
                    foreach($permissionActions as $permission){
                        $module_permission[] = $module->slug . '_' . $permission;
                    }
                    }else{
                        if ($modulePermissions->isNotEmpty()) {

                    // ✅ Use module permissions
                    $module_permission = $modulePermissions->pluck('permission_name'); 
                    // change column if your field name is different

                } else {

                    // ✅ Fallback to role permissions
                    $module_permission = $user->roles()
                    ->whereHas('permissions', function ($q) use ($module) {
                        $q->where('name', 'like', $module->slug . '_%');
                    })
                    ->with(['permissions' => function ($q) use ($module) {
                        $q->where('name', 'like', $module->slug . '_%');
                    }])
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('name')
                    ->values(); // assuming permission column = name
                }
             }
        }else{

            tenancy()->initialize($user->tenant_id);
                // For tenant users, get permissions directly from roles
                $module_permission = $user->roles()
                    ->whereHas('permissions', function ($q) use ($module) {
                        $q->where('name', 'like', $module->slug . '_%');
                    })
                    ->with(['permissions' => function ($q) use ($module) {
                        $q->where('name', 'like', $module->slug . '_%');
                    }])
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('name')
                    ->values(); // assuming permission column = name
                    tenancy()->end();
        }

        // dynamic table name
        $table = Str::plural($module->slug);

        $query = DB::table($table);

        // Search filter
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // latest records
        $data = $query->latest()->paginate(10);

            $fields = ModuleField::select(
            'id',
            'db_column',
            'label',
            'column_type_id',
            'is_multiple',
            'visibility',
            'model_name',
            'created_at'
        )
        ->where('module_id', $module->id)
        ->get();

    $responseFields = [];

    foreach ($fields as $field) {

        $fieldData = [
            'id' => $field->id,
            'name' => $field->db_column,
            'label' => $field->label,
            'type' => $this->mapFieldType($field->column_type_id),
            'is_multiple' => (bool) $field->is_multiple,
            'visibility' => $field->visibility,
            'model_name' => $field->model_name,
            'created_at' => $field->created_at,
        ];

        /*
        |------------------------------------------
        | Dynamic relationship options
        |------------------------------------------
        */
        if (!empty($field->model_name)) {

            $relatedTable = strtolower(Str::plural($field->model_name));

            if (Schema::hasTable($relatedTable)) {

                $options = DB::table($relatedTable)
                    ->select('id as value', 'name as label') // change name column if needed
                    ->get();

                if ($options->isNotEmpty()) {
                    $fieldData['options'] = $options;
                }
            }
        }

        /*
        |------------------------------------------
        | Static options from module_field_options
        |------------------------------------------
        */
        elseif (in_array($field->column_type_id, [5,6])) {

            $options = DB::table('module_field_options')
                ->where('module_field_id', $field->id)
                ->select('option_value as value','option_label as label')
                ->get();

            if ($options->isNotEmpty()) {
                $fieldData['options'] = $options;
            }
        }

        $responseFields[] = $fieldData;
    }

    return response()->json([
        'fields' => $responseFields,
        'data' => $data,
        'action' => $module->actions,
        'module_permission' => $module_permission
    ]);
    }

    //create 
    public function create(Request $request,$slug)
    {
        tenancy()->end();

        $module = $this->getModule($slug);

        $fields = $module->fields;

        // hide relation from JSON response
        $module->makeHidden(['fields']);

        $response = [
            'module' => $module,
            'fields' => []
        ];

        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            $fieldData = [
                'name' => $field->db_column,
                'label' => $field->label,
                'type' => $this->mapFieldType($inputType),
                'is_multiple' => (bool) $field->is_multiple,
                'validation' => $field->validation,
                'tooltip_text' => $field->tooltip_text,
                'is_ckeditor' => $field->is_ckeditor,
                'default_value' => $field->default_value,
                'max_file_size' => $field->max_file_size,
                'order_number' => $field->order_number,
                'visibility' => $field->visibility,
                'is_checked' => $field->is_checked,
            ];

            // static options
            if (($inputType == 5 || $inputType == 6) && !$field->model_name) {

                $options = DB::table('module_field_options')
                    ->where('module_field_id', $field->id)
                    ->get(['option_label', 'option_value']);

                $fieldData['options'] = $options;
            }

            // dynamic options
            if ($field->model_name) {

                $relatedTable = strtolower(Str::plural($field->model_name));

                $options = DB::table($relatedTable)->get();

                $fieldData['options'] = $options;
            }

            $response['fields'][] = $fieldData;
        }

        return response()->json($response);
    }

    private function mapFieldType($type)
    {
       $columnType = ColumnTypes::find($type);

       if($columnType->id == 15){
            return 'photo';
       }
        return $columnType?->input_type;
    }

    /*
    |--------------------------------------------------
    | STORE
    |--------------------------------------------------
    */
    public function store(Request $request, $slug)
    {
        //echo "<pre>"; print_r($request->all()); echo "</pre>"; exit;
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);
        $fk = Str::singular($module->slug);

        $data = [];
        foreach ($module->fields as $field) {
            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            // FILES
            if (in_array($inputType, [14,15])) {
                if (!$field->is_multiple) {
                    $file = $this->handleSingleFile($request, $table, $field);
                    if ($file) {
                        $data[$field->db_column] = $file;
                    }
                }

                continue;
            }

            // NORMAL
            if (!$field->is_multiple) {
                $data[$field->db_column] = $request->{$field->db_column};
            }
        }
        
        $data['created_at'] = now();
        $id = DB::table($table)->insertGetId($data);

        /*
        | MULTIPLE FILES
        */
        foreach ($module->fields as $field) {
            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            if (in_array($inputType, [14,15]) && $field->is_multiple) {
                $filesData = $this->handleMultipleFiles($request, $table, $fk, $field, $id);

                if (!empty($filesData)) {
                    $attachTable = "{$table}_" . Str::plural($field->db_column);
                    DB::table($attachTable)->insert($filesData);
                }
            }
        }

        /*
        | PIVOT
        */
        $this->handlePivot($request, $module, $id);

        return response()->json(['message' => 'Created', 'id' => $id]);
    }

    /*
    |--------------------------------------------------
    | SHOW
    |--------------------------------------------------
    */
    public function show($slug, $id)
    {
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);
        $fk = Str::singular($module->slug);

        // Main data
        $data = DB::table($table)->where('id', $id)->first();

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $response = [
            'data' => (array) $data,
            'relations' => []
        ];

        /*
        |--------------------------------------------------
        | HANDLE RELATIONS (DROPDOWN + SELECTED)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            /*
            |------------------------------------------
            | MANY TO MANY (MULTI SELECT)
            |------------------------------------------
            */
            if ($field->model_name && $field->is_multiple) {

                $relatedTable = strtolower(Str::plural($field->model_name));
                $relatedFk = strtolower(Str::singular($field->model_name));

                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                // All options
                $options = DB::table($relatedTable)->get();

                // Selected IDs
                $selected = DB::table($pivot)
                    ->where("{$fk}_id", $id)
                    ->pluck("{$relatedFk}_id")
                    ->toArray();

                $response['data'][$field->db_column] = [
                    'type' => 'multi_select',
                    'options' => $options,
                    'selected' => $selected
                ];
            }

            /*
            |------------------------------------------
            | BELONGS TO (SINGLE SELECT)
            |------------------------------------------
            */
            elseif ($field->model_name && !$field->is_multiple) {

                $relatedTable = strtolower(Str::plural($field->model_name));

                $options = DB::table($relatedTable)->get();

                $selected = $data->{$field->db_column} ?? null;

                $response['data'][$field->db_column] = [
                    'type' => 'single_select',
                    'options' => $options,
                    'selected' => $selected
                ];
            }
        }

        /*
        |--------------------------------------------------
        | FILES (OPTIONAL - FOR PREVIEW)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? ($field->columnType->column_type_id ?? null);

            if (in_array($inputType, [14,15]) && $field->is_multiple) {

                $attachTable = "{$table}_" . Str::plural($field->db_column);

                $files = DB::table($attachTable)
                    ->where("{$fk}_id", $id)
                    ->get()
                    ->map(function ($file) {
                        $file->file_url = url('storage/' . $file->file_path);
                        return $file;
                    });

                $response['data'][$field->db_column] = $files;
            }

            // Single image URL
            if (in_array($inputType, [14,15]) && !$field->is_multiple && !empty($data->{$field->db_column})) {
                $response['data'][$field->db_column . '_url'] = url('storage/' . $data->{$field->db_column});
            }

            if (in_array($inputType, [5,6])) {

                $options = DB::table('module_field_options')->where('module_field_id', $field->id)->get();

                $selected = $data->{$field->db_column} ?? null;

                $response['data'][$field->db_column] = [
                    'options' => $options,
                    'selected' => $selected
                ];
            }
        }

        return response()->json($response);
    }

    //Edit

    public function edit($slug, $id)
    {
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);
        $fk = Str::singular($module->slug);

        $data = DB::table($table)->where('id', $id)->first();

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $response = [
            'module' => $module,
            'fields' => []
        ];

        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            $fieldData = [
                'name' => $field->db_column,
                'label' => $field->label,
                'type' => $field->columnType->input_type ?? 'text',
                'validation' => $field->validation,
                'tooltip_text' => $field->tooltip_text,
                'is_ckeditor' => $field->is_ckeditor,
                'default_value' => $field->default_value,
                'is_multiple' => (bool) $field->is_multiple,
                'max_file_size' => $field->max_file_size,
                'order_number' => $field->order_number,
                'visibility' => $field->visibility,
                'is_checked' => $field->is_checked,
                'value' => null
            ];

            /*
            |------------------------------------------
            | STATIC SELECT (STATUS, TAGS, ETC)
            |------------------------------------------
            */
            if ($inputType == 3 && !$field->model_name) {

                $options = DB::table('module_field_options')
                    ->where('module_field_id', $field->id)
                    ->get(['option_label', 'option_value']);

                $fieldData['options'] = $options;

                $fieldData['value'] = $field->is_multiple
                    ? json_decode($data->{$field->db_column}, true)
                    : $data->{$field->db_column};

                $response['fields'][] = $fieldData;
                continue;
            }

            if (in_array($inputType, [5,6])) {

                $options = DB::table('module_field_options')->where('module_field_id', $field->id)->get();

                $selected = $data->{$field->db_column} ?? null;

                 $options = DB::table('module_field_options')
                    ->where('module_field_id', $field->id)
                    ->get(['option_label', 'option_value']);

                $fieldData['options'] = $options;

                $fieldData['value'] = $selected;

                $response['fields'][] = $fieldData;
                continue;
            }

            /*
            |------------------------------------------
            | DYNAMIC SELECT (RELATION)
            |------------------------------------------
            */
            if ($field->model_name) {

                $relatedTable = strtolower(Str::plural($field->model_name));
                $relatedFk = strtolower(Str::singular($field->model_name));

                $options = DB::table($relatedTable)->get();
                $fieldData['options'] = $options;

                if ($field->is_multiple) {

                    $tables = [$table, $relatedTable];
                    sort($tables);
                    $pivot = implode('_', $tables);

                    $selected = DB::table($pivot)
                        ->where("{$fk}_id", $id)
                        ->pluck("{$relatedFk}_id")
                        ->toArray();

                    $fieldData['value'] = $selected;

                } else {
                    $fieldData['value'] = $data->{$field->db_column};
                }

                $response['fields'][] = $fieldData;
                continue;
            }

            /*
            |------------------------------------------
            | MULTIPLE FILES / IMAGES
            |------------------------------------------
            */
            if (in_array($inputType, [14,15]) && $field->is_multiple) {

                $attachTable = "{$table}_" . Str::plural($field->db_column);

                $files = DB::table($attachTable)
                    ->where("{$fk}_id", $id)
                    ->get()
                    ->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'name' => $file->file_name,
                            'path' => $file->file_path,
                            'url' => url('storage/' . $file->file_path)
                        ];
                    });

                $fieldData['value'] = $files;

                $response['fields'][] = $fieldData;
                continue;
            }

            /*
            |------------------------------------------
            | SINGLE FILE / IMAGE
            |------------------------------------------
            */
            if (in_array($inputType, [14,15]) && !$field->is_multiple) {

                if (!empty($data->{$field->db_column})) {
                    $fieldData['value'] = [
                        'path' => $data->{$field->db_column},
                        'url' => url('storage/' . $data->{$field->db_column})
                    ];
                }

                $response['fields'][] = $fieldData;
                continue;
            }

            /*
            |------------------------------------------
            | NORMAL FIELD (TEXT, NUMBER, ETC)
            |------------------------------------------
            */
            if ($field->is_multiple && $inputType == 3) {
                $fieldData['value'] = json_decode($data->{$field->db_column}, true);
            } else {
                $fieldData['value'] = $data->{$field->db_column};
            }

            $response['fields'][] = $fieldData;
        }

        return response()->json($response);
    }

    /*
    |--------------------------------------------------
    | UPDATE
    |--------------------------------------------------
    */
    public function update(Request $request, $slug)
    {
        $module = $this->getModule($slug);
        $table  = Str::plural($module->slug);
        $fk     = Str::singular($module->slug);
        $id     = $request->id;

        $data = [];

        /*
        |--------------------------------------------------
        | MAIN TABLE UPDATE (NORMAL + SINGLE FILE)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;
            $column = $field->db_column;

            /*
            |------------------------------------------
            | SINGLE FILE (file + base64)
            |------------------------------------------
            */
            if (in_array($inputType, [14,15]) && !$field->is_multiple) {

                if ($request->hasFile($column) || $request->has($column)) {

                    // delete old file
                    $oldFile = DB::table($table)
                        ->where('id', $id)
                        ->value($column);

                    if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                        Storage::disk('public')->delete($oldFile);
                    }

                    // upload new file
                    $path = $this->handleSingleFile($request, $table, $field);

                    if ($path) {
                        $data[$column] = $path;
                    }
                }

                continue;
            }

            /*
            |------------------------------------------
            | STATIC MULTI SELECT (JSON)
            |------------------------------------------
            */
            if ($field->is_multiple && !$field->model_name && $inputType == 3) {

                $data[$column] = $request->{$column} ?? null;
                continue;
            }

            /*
            |------------------------------------------
            | NORMAL FIELD
            |------------------------------------------
            */
            if (!$field->is_multiple) {

                $data[$column] = $request->{$column};
            }
        }

        // update main table
        $data['updated_at'] = now();
        DB::table($table)->where('id', $id)->update($data);

        /*
        |--------------------------------------------------
        | MULTIPLE FILES (file + base64) - APPEND ONLY
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;
            $column = $field->db_column;

            if (in_array($inputType, [14,15]) && $field->is_multiple) {

                $attachTable = "{$table}_" . Str::plural($column);

                if ($request->hasFile($column) || $request->has($column)) {

                    // 🎯 APPEND NEW FILES (DON'T DELETE OLD ONES)
                    $filesData = $this->handleMultipleFiles($request, $table, $fk, $field, $id);

                    if (!empty($filesData)) {
                        DB::table($attachTable)->insert($filesData);
                    }
                }
            }
        }

        /*
        |--------------------------------------------------
        | PIVOT (MANY TO MANY)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            if ($field->model_name && $field->is_multiple) {

                $relatedTable = strtolower(Str::plural($field->model_name));
                $relatedFk    = strtolower(Str::singular($field->model_name));

                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                // delete old
                DB::table($pivot)
                    ->where("{$fk}_id", $id)
                    ->delete();

                // insert new
                $values = $request->{$field->db_column} ?? [];

                $insertData = [];

                foreach ($values as $val) {
                    $insertData[] = [
                        "{$fk}_id" => $id,
                        "{$relatedFk}_id" => $val,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($insertData)) {
                    DB::table($pivot)->insert($insertData);
                }
            }
        }

        $this->handlePivot($request, $module, $id);

        return response()->json([
            'message' => 'Updated successfully'
        ]);
    }

    /*
    |--------------------------------------------------
    | DELETE
    |--------------------------------------------------
    */
    public function destroy($slug, $id)
    {
        $module = $this->getModule($slug);
        $table  = Str::plural($module->slug);
        $fk     = Str::singular($module->slug);

        $data = DB::table($table)->where('id', $id)->first();

        if (!$data) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /*
        |--------------------------------------------------
        | DELETE FILES (SINGLE + MULTIPLE)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            /*
            |------------------------------------------
            | SINGLE FILE
            |------------------------------------------
            */
            if (in_array($inputType, [14,15]) && !$field->is_multiple) {

                $filePath = $data->{$field->db_column};

                if (!empty($filePath) && Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            /*
            |------------------------------------------
            | MULTIPLE FILES
            |------------------------------------------
            */
            if (in_array($inputType, [14,15]) && $field->is_multiple) {

                $attachTable = "{$table}_" . Str::plural($field->db_column);

                $files = DB::table($attachTable)
                    ->where("{$fk}_id", $id)
                    ->get();

                // 🔥 DELETE FILES FROM STORAGE
                foreach ($files as $file) {
                    if (!empty($file->file_path) &&
                        Storage::disk('public')->exists($file->file_path)) {

                        Storage::disk('public')->delete($file->file_path);
                    }
                }

                // 🔥 DELETE DB RECORDS
                DB::table($attachTable)
                    ->where("{$fk}_id", $id)
                    ->delete();
            }
        }

        /*
        |--------------------------------------------------
        | DELETE PIVOT (MANY TO MANY)
        |--------------------------------------------------
        */
        foreach ($module->fields as $field) {

            if ($field->model_name && $field->is_multiple) {

                $relatedTable = strtolower(Str::plural($field->model_name));

                $tables = [$table, $relatedTable];
                sort($tables);
                $pivot = implode('_', $tables);

                DB::table($pivot)
                    ->where("{$fk}_id", $id)
                    ->delete();
            }
        }

        /*
        |--------------------------------------------------
        | DELETE MAIN RECORD
        |--------------------------------------------------
        */
        DB::table($table)->where('id', $id)->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------
    | DELETE ATTACHMENT (DYNAMIC - FILES & PHOTOS)
    |--------------------------------------------------
    */
    public function deleteAttachment(Request $request, $slug)
    {
        $module = $this->getModule($slug);
        $table  = Str::plural($module->slug);
        $fk     = Str::singular($module->slug);

        $request->validate([
            'attachment_id' => 'required|integer',
            'record_id' => 'required|integer',
            'field_name' => 'nullable|string'  // Optional: specify which field
        ]);

        $attachmentId = $request->attachment_id;
        $recordId = $request->record_id;
        $fieldName = $request->field_name;

        $deleted = false;

        // If field_name is provided, delete from that specific field table
        if ($fieldName) {
            $field = $module->fields->where('db_column', $fieldName)->first();

            if (!$field) {
                return response()->json(['message' => 'Field not found'], 404);
            }

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            // Check if it's a file/photo field
            if (in_array($inputType, [14, 15]) && $field->is_multiple) { // 14=File, 15=Photo

                $attachTable = "{$table}_" . Str::plural($field->db_column);

                $attachment = DB::table($attachTable)
                    ->where('id', $attachmentId)
                    ->where("{$fk}_id", $recordId)
                    ->first();

                if ($attachment) {
                    // Delete from storage
                    if (!empty($attachment->file_path) &&
                        Storage::disk('public')->exists($attachment->file_path)) {
                        Storage::disk('public')->delete($attachment->file_path);
                    }

                    // Delete from database
                    DB::table($attachTable)->where('id', $attachmentId)->delete();
                    $deleted = true;
                }
            }
        } else {
            // Search all file/photo fields (auto-detect)
            foreach ($module->fields as $field) {
                $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

                if (in_array($inputType, [14, 15]) && $field->is_multiple) { // 14=File, 15=Photo

                    $attachTable = "{$table}_" . Str::plural($field->db_column);

                    $attachment = DB::table($attachTable)
                        ->where('id', $attachmentId)
                        ->where("{$fk}_id", $recordId)
                        ->first();

                    if ($attachment) {
                        // Delete from storage
                        if (!empty($attachment->file_path) &&
                            Storage::disk('public')->exists($attachment->file_path)) {
                            Storage::disk('public')->delete($attachment->file_path);
                        }

                        // Delete from database
                        DB::table($attachTable)->where('id', $attachmentId)->delete();

                        $deleted = true;
                        break;
                    }
                }
            }
        }

        if (!$deleted) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        return response()->json([
            'message' => 'Attachment deleted successfully'
        ]);
    }
}