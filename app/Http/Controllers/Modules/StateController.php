<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function index(Request $request)
    {
        $data = State::latest()->paginate(15);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $record = State::create($request->all());
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function show($id)
    {
        $record = State::findOrFail($id);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = State::findOrFail($id);
        $record->update($request->all());
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy($id)
    {
        State::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
