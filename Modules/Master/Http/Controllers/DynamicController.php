<?php

namespace Modules\Master\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;

class DynamicController extends Controller
{
    public function index($slug)
    {
        $module = Module::where('slug', $slug)->with('fields')->firstOrFail();
        $tableName = $module->slug;

        if (!Schema::hasTable($tableName)) {
            $this->createDynamicTable($module);
        }

        $records = DB::table($tableName)->get();
        $records = $this->withRelationData($module, $records);

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
        $multiFileItems = [];

        foreach ($module->fields as $field) {
            $inputType = $field->columnType->input_type;

            if (in_array($inputType, ['file', 'photo'])) {
                if ($field->is_multiple) {
                    if ($request->hasFile($field->db_column)) {
                        $files = $request->file($field->db_column);
                        foreach ($files as $file) {
                            $fileName = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                            $destination = public_path("{$tableName}/{$field->db_column}");
                            if (!file_exists($destination)) {
                                mkdir($destination, 0755, true);
                            }
                            $file->move($destination, $fileName);

                            $multiFileItems[$field->db_column][] = [
                                'file_name' => $fileName,
                                'file_path' => "{$tableName}/{$field->db_column}/{$fileName}",
                                'mime_type' => $file->getClientMimeType(),
                                'file_size' => $file->getSize(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
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

            if ($inputType === 'relation') {
                if ($field->is_multiple) {
                    // relation many-to-many: handled after main insert
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

        $id = DB::table($tableName)->insertGetId($data);

        foreach ($multiFileItems as $column => $items) {
            foreach ($items as $item) {
                $item["{$tableName}_id"] = $id;
                DB::table("{$tableName}_{$column}")->insert($item);
            }
        }

        foreach ($module->fields->filter(fn($f) => ((($f->columnType->input_type === 'relation') || !empty($f->model_name) || Str::endsWith($f->db_column, ['_ids','s'])) && $f->is_multiple)) as $field) {
            $relatedIds = $request->input($field->db_column, []);
            if (!is_array($relatedIds)) {
                $relatedIds = explode(',', (string) $relatedIds);
            }

            $relatedModel = $field->model_name ?: Str::studly(Str::singular(preg_replace('/(_ids?$|s$)/', '', $field->db_column)));
            $relatedTable = Str::plural(Str::snake($relatedModel));
            $pivotTable = "{$tableName}_{$relatedTable}";
            if (!Schema::hasTable($pivotTable)) {
                $pivotTable = "{$tableName}_" . Str::singular($relatedTable);
            }
            $relatedKey = "{$relatedTable}_id";

            foreach ($relatedIds as $relatedId) {
                if (empty($relatedId)) {
                    continue;
                }

                DB::table($pivotTable)->insertOrIgnore([
                    "{$tableName}_id" => $id,
                    $relatedKey => $relatedId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Record created successfully', 'id' => $id]);
    }

    public function show($slug, $id)
    {
        $module = Module::where('slug', $slug)->with('fields')->firstOrFail();
        $tableName = $module->slug;

        $record = DB::table($tableName)->find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $record = $this->withRelationData($module, collect([$record]))->first();

        return response()->json(['success' => true, 'data' => $record]);
    }

    public function edit($slug, $id)
    {
        $module = Module::where('slug', $slug)->with('fields')->firstOrFail();
        $tableName = $module->slug;

        $record = DB::table($tableName)->find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $record = $this->withRelationData($module, collect([$record]))->first();

        return response()->json([
            'success' => true,
            'data' => [
                'record' => $record,
                'module' => [
                    'id' => $module->id,
                    'slug' => $module->slug,
                    'model_name' => $module->model_name,
                    'fields' => $module->fields,
                ],
            ],
        ]);
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
                            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
                            $destination = public_path("{$tableName}/{$field->db_column}");
                            if (!file_exists($destination)) {
                                mkdir($destination, 0755, true);
                            }
                            $file->move($destination, $fileName);

                            DB::table("{$tableName}_{$field->db_column}")->insert([
                                "{$tableName}_id" => $id,
                                'file_name' => $fileName,
                                'file_path' => "{$tableName}/{$field->db_column}/{$fileName}",
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

            if ($inputType === 'relation') {
                if ($field->is_multiple) {
                    // relation many-to-many updates are processed after main record update
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

                foreach ($module->fields->filter(fn($f) => ((($f->columnType->input_type === 'relation') || !empty($f->model_name) || Str::endsWith($f->db_column, ['_ids','s'])) && $f->is_multiple)) as $field) {
            $relatedIds = $request->input($field->db_column, []);
            if (!is_array($relatedIds)) {
                $relatedIds = explode(',', (string) $relatedIds);
            }

            $relatedModel = $field->model_name ?: Str::studly(Str::singular(preg_replace('/(_ids?$|s$)/', '', $field->db_column)));
            $relatedTable = Str::plural(Str::snake($relatedModel));
            $pivotTable = "{$tableName}_{$relatedTable}";
            if (!Schema::hasTable($pivotTable)) {
                $pivotTable = "{$tableName}_" . Str::singular($relatedTable);
            }
            $relatedKey = "{$relatedTable}_id";

            // remove existing relations if full sync
            DB::table($pivotTable)->where("{$tableName}_id", $id)->delete();

            foreach ($relatedIds as $relatedId) {
                if (empty($relatedId)) continue;

                DB::table($pivotTable)->insertOrIgnore([
                    "{$tableName}_id" => $id,
                    $relatedKey => $relatedId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Record updated successfully']);
    }

    public function destroy($slug, $id)
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        $tableName = $module->slug;

        DB::table($tableName)->delete($id);
        return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
    }

    private function withRelationData($module, $records)
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $tableName = $module->slug;
        $primaryIds = $records->pluck('id')->toArray();

        foreach ($module->fields->where('columnType.input_type', 'relation') as $field) {
            $relatedModel = $field->model_name ?: Str::studly(Str::singular(preg_replace('/(_ids?$|s$)/', '', $field->db_column)));
            $relatedTable = Str::plural(Str::snake($relatedModel));

            if ($field->is_multiple) {
                $pivotTable = "{$tableName}_{$relatedTable}";
                if (!Schema::hasTable($pivotTable)) {
                    $pivotTable = "{$tableName}_" . Str::singular($relatedTable);
                }
                $relatedKey = "{$relatedTable}_id";
                $masterKey = "{$tableName}_id";

                $pivotRows = DB::table($pivotTable)
                    ->whereIn($masterKey, $primaryIds)
                    ->get();

                $relatedIdsByMaster = [];
                foreach ($pivotRows as $pivotRow) {
                    $relatedIdsByMaster[$pivotRow->{$masterKey}][] = $pivotRow->{$relatedKey};
                }

                foreach ($records as $record) {
                    $record->{$field->db_column} = $relatedIdsByMaster[$record->id] ?? [];
                }

                // Fetch joined related records for convenience
                $allRelatedIds = array_unique(array_merge([], ...array_values($relatedIdsByMaster)));
                if (!empty($allRelatedIds)) {
                    $relatedRows = DB::table($relatedTable)->whereIn('id', $allRelatedIds)->get()->keyBy('id');
                    foreach ($records as $record) {
                        $record->{$field->db_column . '_data'} = collect($record->{$field->db_column})->map(function ($relId) use ($relatedRows) {
                            return $relatedRows[$relId] ?? null;
                        })->filter()->values();
                    }
                } else {
                    foreach ($records as $record) {
                        $record->{$field->db_column . '_data'} = collect([]);
                    }
                }

            } else {
                $relatedFk = $field->db_column;
                $foreignIds = $records->pluck($relatedFk)->filter()->unique()->toArray();

                $relatedRows = DB::table($relatedTable)->whereIn('id', $foreignIds)->get()->keyBy('id');

                foreach ($records as $record) {
                    $record->{$field->db_column . '_data'} = $relatedRows[$record->{$relatedFk}] ?? null;
                }
            }
        }

        foreach ($module->fields->whereIn('columnType.input_type', ['file', 'photo']) as $field) {
            if (!$field->is_multiple) {
                continue;
            }

            $attachmentTable = "{$tableName}_{$field->db_column}";
            if (!Schema::hasTable($attachmentTable)) {
                continue;
            }

            $attachments = DB::table($attachmentTable)
                ->whereIn("{$tableName}_id", $primaryIds)
                ->get()
                ->groupBy("{$tableName}_id");

            foreach ($records as $record) {
                $record->{$field->db_column} = $attachments[$record->id] ?? collect([]);
            }
        }

        return $records;
    }

    private function createDynamicTable($module)
    {
        Schema::create($module->slug, function ($table) use ($module) {
            $table->id();
            foreach ($module->fields as $field) {
                if (in_array($field->columnType->input_type, ['file', 'photo']) && $field->is_multiple) {
                    // multiple file/photo is stored in separate table
                    continue;
                }

                $type = $field->columnType->db_type;
                if ($type == 'string') {
                    $table->string($field->db_column)->nullable();
                } elseif ($type == 'text') {
                    $table->text($field->db_column)->nullable();
                } elseif ($type == 'integer') {
                    $table->integer($field->db_column)->nullable();
                } elseif ($type == 'boolean') {
                    $table->boolean($field->db_column)->nullable();
                } elseif ($type == 'date') {
                    $table->date($field->db_column)->nullable();
                } elseif ($type == 'datetime') {
                    $table->datetime($field->db_column)->nullable();
                } else {
                    $table->string($field->db_column)->nullable();
                } // Add more types as needed
            }
            $table->timestamps();
        });

        foreach ($module->fields as $field) {
            if (!in_array($field->columnType->input_type, ['file', 'photo']) || !$field->is_multiple) {
                continue;
            }

            $attachmentTable = "{$module->slug}_{$field->db_column}";
            if (!Schema::hasTable($attachmentTable)) {
                Schema::create($attachmentTable, function ($table) use ($module) {
                    $table->id();
                    $table->unsignedBigInteger("{$module->slug}_id");
                    $table->string('file_name');
                    $table->string('file_path');
                    $table->string('mime_type')->nullable();
                    $table->integer('file_size')->nullable();
                    $table->timestamps();
                    $table->foreign("{$module->slug}_id")->references('id')->on($module->slug)->onDelete('cascade');
                });
            }
        }
    }
}
