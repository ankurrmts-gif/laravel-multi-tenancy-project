<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CountryController extends Controller
{
    public function index()
    {
        $records = Country::all();
        return response()->json(['success' => true, 'data' => $records]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $record = Country::create($data);
        return response()->json(['success' => true, 'message' => 'Record created successfully', 'data' => $record]);
    }

    public function show($id)
    {
        $record = Country::findOrFail($id);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = Country::findOrFail($id);
        $record->update($request->all());
        return response()->json(['success' => true, 'message' => 'Record updated successfully']);
    }

    public function destroy($id)
    {
        $record = Country::findOrFail($id);
        $record->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted successfully']);
    }
}