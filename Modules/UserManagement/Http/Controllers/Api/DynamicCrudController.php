<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Module;

class DynamicCrudController extends Controller
{
    // CREATE RECORD
    public function store(Request $request, $slug)
    {
        $module = Module::where('slug', $slug)->firstOrFail();

        DB::table($slug)->insert($request->except('_token'));

        return response()->json([
            'success' => true,
            'message' => 'Data inserted'
        ]);
    }

    // GET ALL
    public function index($slug)
    {
        return response()->json(
            DB::table($slug)->latest()->get()
        );
    }

    // GET SINGLE
    public function show($slug, $id)
    {
        return response()->json(
            DB::table($slug)->where('id', $id)->first()
        );
    }

    // UPDATE
    public function update(Request $request, $slug, $id)
    {
        DB::table($slug)
            ->where('id', $id)
            ->update($request->except('_token'));

        return response()->json([
            'success' => true,
            'message' => 'Updated'
        ]);
    }

    // DELETE
    public function destroy($slug, $id)
    {
        DB::table($slug)->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }
}