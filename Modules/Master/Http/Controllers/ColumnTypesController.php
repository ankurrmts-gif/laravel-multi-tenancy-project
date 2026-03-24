<?php

namespace Modules\Master\Http\Controllers;

use Modules\Master\Models\ColumnType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller;

class ColumnTypesController extends Controller
{
    public function index()
    {
        $types = ColumnType::all();
        return response()->json(['success' => true, 'data' => $types]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'input_type' => 'required|string',
            'db_type' => 'required|string',
            'has_options' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $type = ColumnType::create($request->all());
        return response()->json(['success' => true, 'message' => 'Type created successfully', 'data' => $type]);
    }

    public function show($id)
    {
        $type = ColumnType::findOrFail($id);
        return response()->json(['success' => true, 'data' => $type]);
    }

    public function update(Request $request, $id)
    {
        $type = ColumnType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'input_type' => 'required|string',
            'db_type' => 'required|string',
            'has_options' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $type->update($request->all());
        return response()->json(['success' => true, 'message' => 'Type updated successfully']);
    }

    public function destroy($id)
    {
        $type = ColumnType::findOrFail($id);
        $type->delete();
        return response()->json(['success' => true, 'message' => 'Type deleted successfully']);
    }
}