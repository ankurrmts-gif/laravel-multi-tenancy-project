<?php

namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

class DynamicController extends Controller
{
    public function index($slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $tableName = $module->slug;

        if (!Schema::hasTable($tableName)) {
            $this->createDynamicTable($module);
        }

        $records = DB::table($tableName)->get();
        return response()->json(['data' => $records]);
    }

    public function store(Request $request, $slug)
    {
        $module = Module::where('slug', $slug)->with('fields')->firstOrFail();
        $tableName = $module->slug;

        if (!Schema::hasTable($tableName)) {
            $this->createDynamicTable($module);
        }

        $data = [];
        foreach ($module->fields as $field) {
            $inputType = $field->columnType->input_type;

            if (in_array($inputType, ['file', 'photo'])) {
                if ($field->is_multiple) {
                    if ($request->hasFile($field->db_column)) {
                        $files = $request->file($field->db_column);
                        $savedFiles = [];
                        foreach ($files as $file) {
                            $fileName = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                            $destination = public_path("{$tableName}/{$field->db_column}");
                            if (!file_exists($destination)) {
                                mkdir($destination, 0755, true);
                            }
                            $file->move($destination, $fileName);
                            $savedFiles[] = "{$tableName}/{$field->db_column}/{$fileName}";
                        }
                        // just keep as array of paths in case UI needs it
                        $data[$field->db_column] = json_encode($savedFiles);
                    }
                    continue;
                }

                if ($request->hasFile($field->db_column)) {
                    $file = $request->file($field->db_column);
                    $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                    $destination = public_path("{$tableName}/{$field->db_column}");
                    if (!file_exists($destination)) {
                        mkdir($destination, 0755, true);
                    }
                    $file->move($destination, $fileName);
                    $data[$field->db_column] = "{$tableName}/{$field->db_column}/{$fileName}";
                    continue;
                }
            }

            $value = $request->input($field->db_column);
            if ($field->validation) {
                $validator = Validator::make([$field->db_column => $value], [$field->db_column => $field->validation]);
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }
            }
            $data[$field->db_column] = $value;
        }

        $id = DB::table($tableName)->insertGetId($data);

        // persist multi-file entries into separate table when required
        foreach ($module->fields->filter(function ($field) {
            return in_array($field->columnType->input_type, ['file', 'photo']) && $field->is_multiple;
        }) as $field) {
            if ($request->hasFile($field->db_column)) {
                foreach ($request->file($field->db_column) as $file) {
                    $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                    $destination = public_path("{$tableName}/{$field->db_column}");
                    if (!file_exists($destination)) {
                        mkdir($destination, 0755, true);
                    }
                    $file->move($destination, $fileName);
                    DB::table("{$tableName}_{$field->db_column}")->insert([
                        "{$tableName}_id" => $id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => "{$tableName}/{$field->db_column}/{$fileName}",
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Record created successfully', 'id' => $id]);
    }

    public function show($slug, $id)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $tableName = $module->slug;

        $record = DB::table($tableName)->find($id);
        return response()->json(['data' => $record]);
    }

    public function update(Request $request, $slug, $id)
    {
        $module = Module::where('slug', $slug)->with('fields')->firstOrFail();
        $tableName = $module->slug;

        $data = [];
        foreach ($module->fields as $field) {
            $inputType = $field->columnType->input_type;

            if (in_array($inputType, ['file', 'photo'])) {
                if ($field->is_multiple) {
                    if ($request->hasFile($field->db_column)) {
                        foreach ($request->file($field->db_column) as $file) {
                            DB::table("{$tableName}_{$field->db_column}")->insert([
                                "{$tableName}_id" => $id,
                                'file_name' => $file->getClientOriginalName(),
                                'file_path' => $file->store("public/{$tableName}/{$field->db_column}"),
                                'mime_type' => $file->getClientMimeType(),
                                'file_size' => $file->getSize(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    continue;
                }

                if ($request->hasFile($field->db_column)) {
                    $file = $request->file($field->db_column);
                    $data[$field->db_column] = $file->store("public/{$tableName}/{$field->db_column}");
                    continue;
                }

                $value = $request->input($field->db_column);
                if ($field->validation) {
                    $validator = Validator::make([$field->db_column => $value], [$field->db_column => $field->validation]);
                    if ($validator->fails()) {
                        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                    }
                }
                $data[$field->db_column] = $value;
                continue;
            }

            $value = $request->input($field->db_column);
            if ($field->validation) {
                $validator = Validator::make([$field->db_column => $value], [$field->db_column => $field->validation]);
                if ($validator->fails()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }
            }
            $data[$field->db_column] = $value;
        }

        DB::table($tableName)->where('id', $id)->update($data);
        return response()->json(['success' => true, 'message' => 'Record updated successfully']);
    }

    public function destroy($slug, $id)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $tableName = $module->slug;

        DB::table($tableName)->delete($id);
        return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
    }

    private function createDynamicTable($module)
    {
        Schema::create($module->slug, function ($table) use ($module) {
            $table->id();
            foreach ($module->fields as $field) {
                $type = $field->columnType->db_type;
                if ($type == 'string') {
                    $table->string($field->db_column);
                } elseif ($type == 'text') {
                    $table->text($field->db_column);
                } elseif ($type == 'integer') {
                    $table->integer($field->db_column);
                } elseif ($type == 'boolean') {
                    $table->boolean($field->db_column);
                } elseif ($type == 'date') {
                    $table->date($field->db_column);
                } elseif ($type == 'datetime') {
                    $table->datetime($field->db_column);
                } // Add more types as needed
            }
            $table->timestamps();
        });
    }
}