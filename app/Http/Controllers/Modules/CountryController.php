<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        $data = Country::latest()->paginate(15);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $record = Country::create($request->all());
        return response()->json(['success' => true, 'data' => $record], 201);
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
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy($id)
    {
        Country::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
