<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('forms', App\Http\Controllers\FormController::class)->only('index', 'show', 'store', 'update');
        Route::apiResource('forms.entries', App\Http\Controllers\FormEntryController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
        Route::apiResource('forms.notifications', App\Http\Controllers\FormNotificationController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
    });
});
