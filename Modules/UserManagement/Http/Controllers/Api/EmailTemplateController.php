<?php
 
namespace Modules\UserManagement\Http\Controllers\Api;
 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\DomainOccupiedByOtherTenantException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Settings,App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
 
class EmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $emailTemplates = EmailTemplate::all();
        return response()->json($emailTemplates);
    }

    public function show($id): JsonResponse
    {
        $emailTemplate = EmailTemplate::find($id);
        if (!$emailTemplate) {
            return response()->json(['message' => 'Email Template not found'], 404);
        }
        return response()->json($emailTemplate);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'slug' => 'required|unique:email_tamplate,slug',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $emailTemplate = EmailTemplate::create([
            'title' => $request->title,
            'subject' => $request->subject,
            'slug' => $request->slug,
            'content' => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email Template Created',
            'data' => $emailTemplate
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:email_tamplate,id',
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $emailTemplate = EmailTemplate::find($request->id);
        $emailTemplate->update([
            'title' => $request->title,
            'subject' => $request->subject,
            'content' => $request->content,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Email Template Updated',
            'data' => $emailTemplate
        ]);
    }

}