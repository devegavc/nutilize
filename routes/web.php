<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardInventoryController;
use App\Http\Controllers\DashboardRequestController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OfficeRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('index');

Route::view('/login', 'login')->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');

Route::view('/register', 'register')->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/health/db', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'ok' => true,
            'database' => 'connected',
        ]);
    } catch (\Throwable $throwable) {
        return response()->json([
            'ok' => false,
            'database' => 'unreachable',
            'message' => 'Database connection failed on this network.',
        ], 503);
    }
})->name('health.db');

Route::middleware('auth')->group(function () {
    Route::patch('/dashboard/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');
    Route::view('/dashboard/office/home', 'office-home')->name('office.home');
    Route::get('/dashboard/office/requests', [OfficeRequestController::class, 'index'])->name('office.requests');
    Route::view('/dashboard/office/archive', 'office-archive')->name('office.archive');

    Route::prefix('dashboard')->group(function () {
        Route::middleware('pf-admin')->group(function () {
            Route::view('/home', 'dashboard-home')->name('dashboard.home');
            Route::view('/inventory', 'dashboard-inventory')->name('dashboard.inventory');
            Route::view('/inventory/analytics', 'dashboard-inventory-analytics')->name('dashboard.inventory.analytics');
            Route::get('/inventory/equipments', [DashboardInventoryController::class, 'equipments'])->name('dashboard.inventory.equipments');
            Route::post('/inventory/equipments', [DashboardInventoryController::class, 'storeEquipment'])->name('dashboard.inventory.equipments.store');
            Route::patch('/inventory/equipments/{itemId}', [DashboardInventoryController::class, 'updateEquipment'])->name('dashboard.inventory.equipments.update');
            Route::get('/inventory/facilities', [DashboardInventoryController::class, 'facilities'])->name('dashboard.inventory.facilities');
            Route::post('/inventory/facilities', [DashboardInventoryController::class, 'storeFacility'])->name('dashboard.inventory.facilities.store');
            Route::patch('/inventory/facilities/{roomId}', [DashboardInventoryController::class, 'updateFacility'])->name('dashboard.inventory.facilities.update');
            Route::get('/maintenance', [DashboardInventoryController::class, 'maintenance'])->name('dashboard.maintenance');
            Route::view('/messages', 'dashboard-messages')->name('dashboard.messages');
            Route::view('/schedule', 'dashboard-schedule')->name('dashboard.schedule');
        });

        Route::view('/history', 'dashboard-history')->name('dashboard.history');
        Route::view('/profile', 'dashboard-profile')->name('dashboard.profile');
        Route::get('/request', [DashboardRequestController::class, 'index'])->name('dashboard.request');

        // Approval routes for Physical Facilities admin
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('dashboard.approvals');
        Route::patch('/approval/{approvalId}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
        Route::patch('/approval/{approvalId}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
        Route::patch('/request/{reservationId}/final-approve', [ApprovalController::class, 'finalApproveReservation'])->name('request.final.approve');
        Route::patch('/request/{reservationId}/final-reject', [ApprovalController::class, 'finalRejectReservation'])->name('request.final.reject');
    });
});
