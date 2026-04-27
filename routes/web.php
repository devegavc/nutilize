<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardHomeController;
use App\Http\Controllers\DashboardHistoryController;
use App\Http\Controllers\DashboardInventoryController;
use App\Http\Controllers\DashboardScheduleController;
use App\Http\Controllers\DashboardRequestController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OfficeArchiveController;
use App\Http\Controllers\OfficeItemController;
use App\Http\Controllers\OfficeRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::view('/', 'index')->name('index');

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
    Route::get('/dashboard/office/home', [OfficeRequestController::class, 'index'])->name('office.home');
    Route::get('/dashboard/office/requests', fn () => redirect()->route('office.home'))->name('office.requests');
    Route::get('/dashboard/office/items', [OfficeItemController::class, 'index'])->name('office.items');
    Route::post('/dashboard/office/items', [OfficeItemController::class, 'store'])->name('office.items.store');
    Route::patch('/dashboard/office/items/{itemId}', [OfficeItemController::class, 'update'])->name('office.items.update');
    Route::delete('/dashboard/office/items/{itemId}', [OfficeItemController::class, 'destroy'])->name('office.items.destroy');
    Route::get('/dashboard/office/items/maintenance', [OfficeItemController::class, 'maintenance'])->name('office.items.maintenance');
    Route::patch('/dashboard/office/items/maintenance/units/{unitId}', [OfficeItemController::class, 'updateMaintenanceUnit'])->name('office.items.maintenance.units.update');
    Route::get('/dashboard/office/history', [OfficeArchiveController::class, 'index'])->name('office.history');

    Route::prefix('dashboard')->group(function () {
        Route::middleware('pf-admin')->group(function () {
            Route::get('/home', [DashboardHomeController::class, 'index'])->name('dashboard.home');
            Route::get('/inventory', [DashboardInventoryController::class, 'index'])->name('dashboard.inventory');
            Route::get('/inventory/analytics', [DashboardInventoryController::class, 'analytics'])->name('dashboard.inventory.analytics');
            Route::get('/inventory/equipments', [DashboardInventoryController::class, 'equipments'])->name('dashboard.inventory.equipments');
            Route::post('/inventory/equipment-categories', [DashboardInventoryController::class, 'storeEquipmentCategory'])->name('dashboard.inventory.equipment-categories.store');
            Route::patch('/inventory/equipment-categories/{categoryId}', [DashboardInventoryController::class, 'updateEquipmentCategory'])->name('dashboard.inventory.equipment-categories.update');
            Route::delete('/inventory/equipment-categories/{categoryId}', [DashboardInventoryController::class, 'destroyEquipmentCategory'])->name('dashboard.inventory.equipment-categories.destroy');
            Route::post('/inventory/equipments', [DashboardInventoryController::class, 'storeEquipment'])->name('dashboard.inventory.equipments.store');
            Route::patch('/inventory/equipments/{itemId}', [DashboardInventoryController::class, 'updateEquipment'])->name('dashboard.inventory.equipments.update');
            Route::delete('/inventory/equipments/{itemId}', [DashboardInventoryController::class, 'destroyEquipment'])->name('dashboard.inventory.equipments.destroy');
            Route::get('/inventory/facilities', [DashboardInventoryController::class, 'facilities'])->name('dashboard.inventory.facilities');
            Route::post('/inventory/facilities', [DashboardInventoryController::class, 'storeFacility'])->name('dashboard.inventory.facilities.store');
            Route::patch('/inventory/facilities/{roomId}', [DashboardInventoryController::class, 'updateFacility'])->name('dashboard.inventory.facilities.update');
            Route::get('/maintenance', [DashboardInventoryController::class, 'maintenance'])->name('dashboard.maintenance');
            Route::patch('/maintenance/units/{unitId}', [DashboardInventoryController::class, 'updateMaintenanceUnit'])->name('dashboard.maintenance.units.update');
            Route::view('/messages', 'dashboard-messages')->name('dashboard.messages');
            Route::get('/schedule', [DashboardScheduleController::class, 'index'])->name('dashboard.schedule');
        });

        Route::get('/history', [DashboardHistoryController::class, 'index'])->name('dashboard.history');
        Route::view('/profile', 'dashboard-profile')->name('dashboard.profile');
        Route::get('/request', [DashboardRequestController::class, 'index'])->name('dashboard.request');
        Route::get('/request/list', [DashboardRequestController::class, 'requestList'])->name('dashboard.request.list');

        // Approval routes for Physical Facilities admin
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('dashboard.approvals');
        Route::patch('/approval/{approvalId}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
        Route::patch('/approval/{approvalId}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
        Route::patch('/request/{reservationId}/final-approve', [ApprovalController::class, 'finalApproveReservation'])->name('request.final.approve');
        Route::patch('/request/{reservationId}/final-reject', [ApprovalController::class, 'finalRejectReservation'])->name('request.final.reject');
    });
});
