<?php

namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return Module::with('assignedAdmins', 'assignedAgencies', 'permissions')
            ->where('slug', $slug)
            ->firstOrFail();
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
        if ($request->hasFile($field->db_column)) {

            $folder = "public/{$table}";
            $this->ensureFolder($folder);

            return $request->file($field->db_column)
                ->store($table, 'public');
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
        if (!$request->hasFile($field->db_column)) {
            return [];
        }

        $folder = "public/{$table}/{$field->db_column}";
        $this->ensureFolder($folder);

        $filesData = [];

        foreach ($request->file($field->db_column) as $file) {
            $filesData[] = [
                "{$fk}_id" => $recordId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->store("{$table}/{$field->db_column}", 'public'),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
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
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);

        $query = DB::table($table);

        // Search
        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $data = $query->latest()->paginate(10);

        return response()->json($data);
    }

    //create 
    public function create($slug)
    {
        $module = $this->getModule($slug);

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
                'is_multiple' => $field->is_multiple,
                'max_file_size' => $field->max_file_size,
                'order_number' => $field->order_number,
                'visibility' => $field->visibility,
                'is_checked' => $field->is_checked,
            ];

            /*
            |--------------------------------------------------
            | STATIC SELECT OPTIONS
            |--------------------------------------------------
            */
            if (($inputType == 5 || $inputType == 6) && !$field->model_name) {

                $options = DB::table('module_field_options')
                    ->where('module_field_id', $field->id)
                    ->get(['option_label', 'option_value']);

                $fieldData['options'] = $options;
            }

            /*
            |--------------------------------------------------
            | DYNAMIC SELECT OPTIONS (RELATION)
            |--------------------------------------------------
            */
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

                $response['relations'][$field->db_column] = [
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

                $response['relations'][$field->db_column] = [
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
                'is_multiple' => (bool) $field->is_multiple,
                'validation' => $field->validation,
                'value' => null,
            ];

            /*
            |--------------------------------------------------
            | NORMAL FIELD VALUE
            |--------------------------------------------------
            */
            if (!$field->is_multiple && !in_array($inputType, [14,15])) {
                $fieldData['value'] = $data->{$field->db_column};
            }

            /*
            |--------------------------------------------------
            | STATIC SELECT
            |--------------------------------------------------
            */
            if ($inputType == 3 && !$field->model_name) {

                $options = DB::table('module_field_options')
                    ->where('module_field_id', $field->id)
                    ->get(['label','value']);

                $selected = $field->is_multiple
                    ? json_decode($data->{$field->db_column}, true)
                    : $data->{$field->db_column};

                $fieldData['options'] = $options;
                $fieldData['value'] = $selected;
            }

            /*
            |--------------------------------------------------
            | DYNAMIC SELECT
            |--------------------------------------------------
            */
            if ($inputType == 3 && $field->model_name) {

                $relatedTable = strtolower(Str::plural($field->model_name));
                $relatedFk = strtolower(Str::singular($field->model_name));

                $options = DB::table($relatedTable)->get();

                if ($field->is_multiple) {

                    $tables = [$table, $relatedTable];
                    sort($tables);
                    $pivot = implode('_', $tables);

                    $selected = DB::table($pivot)
                        ->where("{$fk}_id", $id)
                        ->pluck("{$relatedFk}_id")
                        ->toArray();

                } else {
                    $selected = $data->{$field->db_column};
                }

                $fieldData['options'] = $options;
                $fieldData['value'] = $selected;
            }

            /*
            |--------------------------------------------------
            | SINGLE FILE / IMAGE
            |--------------------------------------------------
            */
            if (in_array($inputType, [14,15]) && !$field->is_multiple) {

                if (!empty($data->{$field->db_column})) {
                    $fieldData['value'] = [
                        'path' => $data->{$field->db_column},
                        'url' => url('storage/' . $data->{$field->db_column})
                    ];
                }
            }

            /*
            |--------------------------------------------------
            | MULTIPLE FILES / IMAGES
            |--------------------------------------------------
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
    public function update(Request $request, $slug, $id)
    {
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);
        $fk = Str::singular($module->slug);

        $data = [];

        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            if (in_array($inputType, [14,15])) {

                if (!$field->is_multiple) {
                    $file = $this->handleSingleFile($request, $table, $field);
                    if ($file) {
                        $data[$field->db_column] = $file;
                    }
                }

                continue;
            }

            if (!$field->is_multiple) {
                $data[$field->db_column] = $request->{$field->db_column};
            }
        }

        DB::table($table)->where('id', $id)->update($data);

        /*
        | MULTIPLE FILES (OPTIONAL: delete old)
        */
        foreach ($module->fields as $field) {

            $inputType = $field->column_type_id ?? $field->columnType->column_type_id;

            if (in_array($inputType, [14,15]) && $field->is_multiple) {

                $attachTable = "{$table}_" . Str::plural($field->db_column);

                DB::table($attachTable)->where("{$fk}_id", $id)->delete();

                $filesData = $this->handleMultipleFiles($request, $table, $fk, $field, $id);

                if (!empty($filesData)) {
                    DB::table($attachTable)->insert($filesData);
                }
            }
        }

        /*
        | PIVOT
        */
        $this->handlePivot($request, $module, $id);

        return response()->json(['message' => 'Updated']);
    }

    /*
    |--------------------------------------------------
    | DELETE
    |--------------------------------------------------
    */
    public function destroy($slug, $id)
    {
        $module = $this->getModule($slug);
        $table = Str::plural($module->slug);

        DB::table($table)->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}