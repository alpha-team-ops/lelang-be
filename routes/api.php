<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationSetupController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\BidController;
use App\Http\Controllers\Api\V1\WinnerBidController;
use App\Http\Controllers\Api\V1\ImageUploadController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Admin\AdminAuctionController;
use App\Http\Controllers\Api\V1\Portal\PortalAuctionController;

// API v1 Routes
Route::prefix('v1')->group(function () {
    // Public Health Check Route
    Route::get('/health', [HealthController::class, 'check']);

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

    // ==================== ADMIN AUCTION ROUTES ====================
    // Protected Admin Auction Routes (CRUD operations)
    Route::prefix('admin/auctions')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::get('/', [AdminAuctionController::class, 'index'])->middleware('permission:manage_auctions');
        Route::post('/', [AdminAuctionController::class, 'store'])->middleware('permission:manage_auctions');
        Route::get('/status/{status}', [AdminAuctionController::class, 'getByStatus'])->middleware('permission:manage_auctions');
        Route::get('/{id}', [AdminAuctionController::class, 'show'])->middleware('permission:manage_auctions');
        Route::put('/{id}', [AdminAuctionController::class, 'update'])->middleware('permission:manage_auctions');
        Route::delete('/{id}', [AdminAuctionController::class, 'destroy'])->middleware('permission:manage_auctions');
    });

    // ==================== PORTAL AUCTION ROUTES ====================
    // Public Portal Auction Routes (read-only)
    Route::prefix('auctions')->group(function () {
        Route::get('/', [PortalAuctionController::class, 'list']);
        Route::get('/search', [PortalAuctionController::class, 'search']);
        Route::get('/category/{category}', [PortalAuctionController::class, 'getByCategory']);
        Route::get('/{id}', [PortalAuctionController::class, 'show']);
        Route::post('/{id}/view', [PortalAuctionController::class, 'recordView']);
    });

    // Public Bid Routes (Portal - Activity and History don't need auth)
    Route::prefix('bids')->group(function () {
        Route::get('/activity', [BidController::class, 'activity']);
        Route::get('/auction/{auctionId}', [BidController::class, 'getAuctionBids']);
        Route::get('/user/{userId}', [BidController::class, 'userHistory']);
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
        Route::put('/{id}/payment-due-date', [WinnerBidController::class, 'updatePaymentDueDate'])->middleware('permission:manage_auctions');
    });

    // Image Upload Routes
    Route::prefix('images')->middleware(\App\Http\Middleware\AuthenticateApiToken::class)->group(function () {
        Route::post('/upload', [ImageUploadController::class, 'upload'])->middleware('permission:manage_auctions');
        Route::post('/bulk-upload', [ImageUploadController::class, 'bulkUpload'])->middleware('permission:manage_auctions');
        Route::delete('/{path}', [ImageUploadController::class, 'delete'])->middleware('permission:manage_auctions')->where('path', '.*');
        Route::get('/url/{path}', [ImageUploadController::class, 'getUrl'])->where('path', '.*');
    });
});

