<?php

use Illuminate\Support\Facades\Route;
use Modules\Master\Http\Controllers\ColumnTypesController;
use Modules\Master\Http\Controllers\ModulesController;
use Modules\Master\Http\Controllers\DynamicController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Column Types APIs
Route::apiResource('types', ColumnTypesController::class);

// Modules APIs
Route::get('get-parent-menu', [ModulesController::class, 'getParentMenu']);
Route::get('get-admins', [ModulesController::class, 'getAdmins']);
Route::get('get-models', [ModulesController::class, 'getModels']);
Route::get('get-Model-fields/{model_name}', [ModulesController::class, 'getModelFields']);

Route::apiResource('modules', ModulesController::class);
Route::get('modules-with-fields/{id}', [ModulesController::class, 'showWithFields']);
Route::put('modules-with-fields/{id}', [ModulesController::class, 'updateWithFields']);
Route::delete('modules-with-fields/{id}', [ModulesController::class, 'destroyWithFields']);
Route::delete('module-fields/{id}', [ModulesController::class, 'deleteField']);
Route::delete('module-field-options/{id}', [ModulesController::class, 'deleteFieldOption']);
Route::post('module-fields/reorder', [ModulesController::class, 'reorderFields']);
Route::patch('module-fields/{id}/status', [ModulesController::class, 'updateFieldStatus']);
Route::patch('modules/{id}/status', [ModulesController::class, 'updateModuleStatus']);
Route::post('module-field-options', [ModulesController::class, 'updateFieldOptions']);

// Dynamic CRUD APIs
Route::get('dynamic/{slug}', [DynamicController::class, 'index']);
Route::post('dynamic/{slug}', [DynamicController::class, 'store']);
Route::get('dynamic/{slug}/{id}', [DynamicController::class, 'show']);
Route::put('dynamic/{slug}/{id}', [DynamicController::class, 'update']);
Route::delete('dynamic/{slug}/{id}', [DynamicController::class, 'destroy']);
