<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Modules\UserManagement\Http\Controllers\Api\RoleController;
use Modules\UserManagement\Http\Controllers\Api\PermissionController;
use Modules\UserManagement\Http\Controllers\Api\AuthController;
use Modules\UserManagement\Http\Controllers\Api\InvitateUserController;
use Modules\UserManagement\Http\Controllers\Api\CommonController;
use Modules\UserManagement\Http\Controllers\Api\UserController;
use Modules\UserManagement\Http\Controllers\Api\APUserManagementController;
use Modules\UserManagement\Http\Controllers\Api\ModuleController;
use Modules\UserManagement\Http\Controllers\Api\DynamicCrudController;

// Use AuthenticateSanctumMultiTenant so tokens are resolved from central or tenant DBs.
Route::middleware([\App\Http\Middleware\AuthenticateSanctumMultiTenant::class])->group(function () {

    // Role routes
    Route::prefix('roles')->group(function () {
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
        Route::post('/assign/{roleId}', [RoleController::class, 'assignToUser']);
    });

    // Permission routes
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::put('/{id}', [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);
        Route::post('/assign/{permissionId}', [PermissionController::class, 'assignToUser']);
    });
});
    
// Public API routes
Route::post('/verify-Mfa', [AuthController::class, 'verifyMfa']);

Route::middleware(['verify.recaptcha', 'throttle:auth-limit'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Route::middleware('auth:sanctum')->get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
//     $request->fulfill();
//     return response()->json(['message' => 'Email verified successfully']);
// })->name('verification.verify');

// Route::middleware('auth:sanctum')->post('/email/verification-notification', function (Request $request) {
//     $request->user()->sendEmailVerificationNotification();
//     return response()->json(['message' => 'Verification link sent']);
// })->name('verification.send');

Route::middleware('auth:sanctum')->post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
Route::post('/accept-invitation', [InvitateUserController::class, 'accept']);

Route::post('/refresh', [AuthController::class, 'refreshToken']);

Route::middleware(\App\Http\Middleware\AuthenticateSanctumMultiTenant::class)->group(function () {
    Route::get('/invitation-list', [InvitateUserController::class, 'invitationList']);
    Route::get('/invitation-details', [InvitateUserController::class, 'invitationDetails']);
    Route::post('invite', [InvitateUserController::class, 'invite']);
    Route::post('resend-invite', [InvitateUserController::class, 'resendInvite']);
    
    Route::get('/user', [AuthController::class, 'userDetails']); // Get login user details
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('update-profile', [AuthController::class, 'updateProfile']);
    Route::post('change-password', [AuthController::class, 'changePassword']);

    Route::get('dashboard', [CommonController::class, 'deshboardCount']);
    
    //Users Apis
    Route::get('users', [UserController::class, 'getUsers']); // Get all admin and agency users
    // Route::get('agents', [UserController::class, 'getAgents']);
    Route::get('user-details', [UserController::class, 'getUserDetails']);
    Route::post('update-user', [UserController::class, 'updateUser']);
    Route::delete('/user/{id}', [UserController::class, 'deleteUser']);
    Route::get('get-agency', [UserController::class, 'getAgency']);
    Route::get('get-admin', [UserController::class, 'getAdmin']);

    Route::get('get-all-settings', [CommonController::class, 'getAllSettings']);
    Route::get('get-setting-details', [CommonController::class, 'getSettingDetails']);
    Route::post('add-settings', [CommonController::class, 'addSettings']);
    Route::post('update-settings', [CommonController::class, 'updateSettings']);

    Route::get('/get-column-types', [CommonController::class, 'getColumnTypes']);
    Route::get('/get-all-models', [CommonController::class, 'getAllModels']);
    Route::get('/get-all-model-fields/{model_name}', [CommonController::class, 'getAllModelFields']);

    Route::prefix('modules')->group(function () {
        Route::post('/', [ModuleController::class, 'store']);
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('{id}', [ModuleController::class, 'show']);
        Route::put('{id}', [ModuleController::class, 'update']);
        Route::delete('{id}', [ModuleController::class, 'destroy']);
    });

    Route::prefix('dynamic/{slug}')->group(function () {
        Route::post('/', [DynamicCrudController::class, 'store']);
        Route::get('/', [DynamicCrudController::class, 'index']);
        Route::get('{id}', [DynamicCrudController::class, 'show']);
        Route::put('{id}', [DynamicCrudController::class, 'update']);
        Route::delete('{id}', [DynamicCrudController::class, 'destroy']);
    });
});