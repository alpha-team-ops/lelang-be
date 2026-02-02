<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationSetupController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\AuctionController;
use App\Http\Controllers\Api\V1\BidController;
use App\Http\Controllers\Api\V1\WinnerBidController;

// API v1 Routes
Route::prefix('v1')->group(function () {
    // Public Auth Routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/portal-login', [AuthController::class, 'portalLogin']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        // Protected Auth Routes
        Route::middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
            Route::get('/verify', [AuthController::class, 'verify']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/check-permission', [AuthController::class, 'checkPermission']);
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
        Route::post('/', [StaffController::class, 'store'])->middleware('permission:manage_staff');
        Route::get('/{id}', [StaffController::class, 'show']);
        Route::put('/{id}', [StaffController::class, 'update'])->middleware('permission:manage_staff');
        Route::delete('/{id}', [StaffController::class, 'destroy'])->middleware('permission:manage_staff');
        Route::put('/{id}/activity', [StaffController::class, 'updateActivity']);
    });

    // Protected Role Routes
    Route::prefix('roles')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:manage_roles');
        Route::get('/permissions/all', [RoleController::class, 'getPermissions']);
        Route::post('/{id}/assign', [RoleController::class, 'assignRole'])->middleware('permission:manage_roles');
        Route::delete('/{id}/unassign', [RoleController::class, 'unassignRole'])->middleware('permission:manage_roles');
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:manage_roles');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:manage_roles');
    });

    // Public Auction Routes (Portal) - WITH AUTHENTICATION
    Route::prefix('auctions/portal')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/list', [AuctionController::class, 'portalList']);
        Route::get('/{id}', [AuctionController::class, 'portalShow']);
    });

    Route::prefix('auctions')->group(function () {
        Route::get('/search', [AuctionController::class, 'search']);
        Route::get('/category/{category}', [AuctionController::class, 'getByCategory']);
        Route::post('/{id}/view', [AuctionController::class, 'recordView']);
    });

    // Public Bid Routes (Portal - Activity and History don't need auth)
    Route::prefix('bids')->group(function () {
        Route::get('/activity', [BidController::class, 'activity']);
        Route::get('/auction/{auctionId}', [BidController::class, 'getAuctionBids']);
        Route::get('/user/{userId}', [BidController::class, 'userHistory']);
    });

    // Protected Auction Routes (Admin)
    Route::prefix('auctions')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [AuctionController::class, 'index']);
        Route::post('/', [AuctionController::class, 'store'])->middleware('permission:manage_auctions');
        Route::get('/{id}', [AuctionController::class, 'show']);
        Route::put('/{id}', [AuctionController::class, 'update'])->middleware('permission:manage_auctions');
        Route::delete('/{id}', [AuctionController::class, 'destroy'])->middleware('permission:manage_auctions');
        Route::get('/status/{status}', [AuctionController::class, 'getByStatus']);
    });

    // Protected Bid Routes (Portal User)
    Route::prefix('bids')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::post('/place', [BidController::class, 'place']);
    });

    // Winner Bids Routes (Admin)
    Route::prefix('bids/winners')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [WinnerBidController::class, 'index'])->middleware('permission:manage_auctions');
        Route::post('/', [WinnerBidController::class, 'create'])->middleware('permission:manage_auctions');
        Route::get('/overdue-payments', [WinnerBidController::class, 'overduePayments'])->middleware('permission:manage_auctions');
        Route::get('/status/{status}', [WinnerBidController::class, 'byStatus'])->middleware('permission:manage_auctions');
        Route::get('/{id}', [WinnerBidController::class, 'show'])->middleware('permission:manage_auctions');
        Route::get('/{id}/history', [WinnerBidController::class, 'statusHistory'])->middleware('permission:manage_auctions');
        Route::put('/{id}/status', [WinnerBidController::class, 'updateStatus'])->middleware('permission:manage_auctions');
    });
});
