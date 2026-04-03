<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $data = Project::latest()->paginate(15);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $record = Project::create($request->all());
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function show($id)
    {
        $record = Project::findOrFail($id);
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function update(Request $request, $id)
    {
        $record = Project::findOrFail($id);
        $record->update($request->all());
        return response()->json(['success' => true, 'data' => $record]);
    }

    public function destroy($id)
    {
        Project::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
