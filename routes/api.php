<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationSetupController;

// API v1 Routes
Route::prefix('v1')->group(function () {
    // Public Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected Auth Routes
        Route::middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
            Route::get('/verify', [AuthController::class, 'verify']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });
    });

    // Protected Organization Routes
    Route::prefix('organization')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/code', [OrganizationController::class, 'getOrganizationCode']);
        Route::get('/settings', [OrganizationController::class, 'getSettings']);
        Route::put('/settings', [OrganizationController::class, 'updateSettings']);
        Route::post('/logo', [OrganizationController::class, 'uploadLogo']);
        Route::get('/check-setup', [OrganizationSetupController::class, 'checkSetup']);
        Route::post('/create', [OrganizationSetupController::class, 'create']);
        Route::post('/join', [OrganizationSetupController::class, 'join']);
    });
});
