<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormEntryController;
use App\Http\Controllers\FormNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        Route::middleware('auth:api')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::apiResource('forms', FormController::class)->only('index', 'show', 'store', 'update', 'destroy');
        Route::post('forms/{form}/restore', [FormController::class, 'restore'])
            ->withTrashed()
            ->name('forms.restore');
        Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])
            ->name('forms.duplicate');
        Route::apiResource('forms.entries', FormEntryController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
        Route::apiResource('forms.notifications', FormNotificationController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
    });
});
