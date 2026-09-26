<?php

use Illuminate\Support\Facades\Route;

Route::prefix('forms')->group(function () {
    Route::resource('/', App\Http\Controllers\FormController::class)->only('index', 'show', 'store', 'update', 'destroy');
    Route::resource('{form}/entries', App\Http\Controllers\FormEntryController::class)->only('index', 'store', 'update');
    Route::resource('{form}/notifications', App\Http\Controllers\FormNotificationController::class)->only('index', 'store', 'create', 'edit', 'destroy');
});
