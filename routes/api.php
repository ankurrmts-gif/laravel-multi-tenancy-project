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
use Modules\UserManagement\Http\Controllers\UserManagementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// UserManagement Module Routes - Public
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

// Public API routes
Route::post('/verify-Mfa', [AuthController::class, 'verifyMfa']);

Route::middleware(['verify.recaptcha', 'throttle:auth-limit'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

