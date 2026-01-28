<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationSetupController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\AuctionController;

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

    // Protected Staff Routes
    Route::prefix('staff')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [StaffController::class, 'index']);
        Route::post('/', [StaffController::class, 'store']);
        Route::get('/{id}', [StaffController::class, 'show']);
        Route::put('/{id}', [StaffController::class, 'update']);
        Route::delete('/{id}', [StaffController::class, 'destroy']);
        Route::put('/{id}/activity', [StaffController::class, 'updateActivity']);
    });

    // Protected Role Routes
    Route::prefix('roles')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/permissions/all', [RoleController::class, 'getPermissions']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
        Route::post('/{id}/assign', [RoleController::class, 'assignRole']);
        Route::delete('/{id}/unassign', [RoleController::class, 'unassignRole']);
    });

    // Protected Auction Routes (Admin)
    Route::prefix('auctions')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [AuctionController::class, 'index']);
        Route::post('/', [AuctionController::class, 'store']);
        Route::get('/{id}', [AuctionController::class, 'show']);
        Route::put('/{id}', [AuctionController::class, 'update']);
        Route::delete('/{id}', [AuctionController::class, 'destroy']);
        Route::get('/status/{status}', [AuctionController::class, 'getByStatus']);
    });

    // Public Auction Routes (Portal)
    Route::prefix('auctions')->group(function () {
        Route::get('/portal/list', [AuctionController::class, 'portalList']);
        Route::get('/portal/{id}', [AuctionController::class, 'portalShow']);
        Route::get('/search', [AuctionController::class, 'search']);
        Route::get('/category/{category}', [AuctionController::class, 'getByCategory']);
    });
});
