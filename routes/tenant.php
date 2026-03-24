<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Modules\Master\Http\Controllers\DynamicController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Dynamic CRUD APIs
Route::get('dynamic/{slug}', [DynamicController::class, 'index']);
Route::post('dynamic/{slug}', [DynamicController::class, 'store']);
Route::get('dynamic/{slug}/{id}', [DynamicController::class, 'show']);
Route::put('dynamic/{slug}/{id}', [DynamicController::class, 'update']);
Route::delete('dynamic/{slug}/{id}', [DynamicController::class, 'destroy']);

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });
});
