<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormEntryController;
use App\Http\Controllers\FormEntryExportController;
use App\Http\Controllers\FormNotificationController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        Route::middleware('throttle:6,1')->group(function () {
            Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('forgot-password');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('reset-password');
        });

        Route::middleware(['auth:api', 'token.current'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::patch('me', [AccountController::class, 'update'])->name('me.update');
            Route::delete('me', [AccountController::class, 'destroy'])->name('me.destroy');
            Route::put('password', [AccountController::class, 'updatePassword'])->name('password.update');
        });
    });

    Route::middleware(['auth:api', 'token.current'])->group(function () {
        Route::apiResource('forms', FormController::class)->only('index', 'show', 'store', 'update', 'destroy');
        Route::post('forms/{form}/restore', [FormController::class, 'restore'])
            ->withTrashed()
            ->name('forms.restore');
        Route::post('forms/{form}/duplicate', [FormController::class, 'duplicate'])
            ->name('forms.duplicate');
        Route::post('forms/{form}/entries/exports', [FormEntryExportController::class, 'store'])
            ->name('forms.entries.exports.store');
        Route::get('entry-exports/{export}', [FormEntryExportController::class, 'show'])
            ->name('entry-exports.show');
        Route::get('entry-exports/{export}/download', [FormEntryExportController::class, 'download'])
            ->name('entry-exports.download');
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
