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
        Route::get('forms/{form}/entries/export', [FormEntryController::class, 'export'])
            ->name('forms.entries.export');
        Route::post('forms/{form}/entries/bulk', [FormEntryController::class, 'bulk'])
            ->name('forms.entries.bulk');
        Route::apiResource('forms.entries', FormEntryController::class)
            ->only('index', 'show', 'store', 'update', 'destroy')
            ->shallow();
        Route::post('entries/{entry}/restore', [FormEntryController::class, 'restore'])
            ->withTrashed()
            ->name('entries.restore');
        Route::delete('entries/{entry}/force', [FormEntryController::class, 'forceDestroy'])
            ->withTrashed()
            ->name('entries.force-destroy');
        Route::apiResource('forms.notifications', FormNotificationController::class)
            ->only('index', 'show', 'store', 'update')
            ->shallow();
    });
});
