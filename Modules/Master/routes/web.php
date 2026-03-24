<?php

use Illuminate\Support\Facades\Route;
use Modules\Master\Http\Controllers\MasterController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('masters', MasterController::class)->names('master');
});
