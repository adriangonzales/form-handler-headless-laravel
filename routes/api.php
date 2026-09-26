<?php

use App\Http\Controllers\FormController;
use App\Http\Controllers\FormEntryController;
use App\Http\Controllers\FormNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('forms', FormController::class)->only('index', 'show', 'store', 'update');
        Route::apiResource('forms.entries', FormEntryController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
        Route::apiResource('forms.notifications', FormNotificationController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
    });
});
