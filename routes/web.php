<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard.home')
        : redirect()->route('login');
})->name('index');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.authenticate');

    Route::view('/register', 'register')->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::patch('/dashboard/profile', [ProfileController::class, 'update'])->name('dashboard.profile.update');

    Route::prefix('dashboard')->group(function () {
        Route::view('/home', 'dashboard-home')->name('dashboard.home');
        Route::view('/history', 'dashboard-history')->name('dashboard.history');
        Route::view('/inventory', 'dashboard-inventory')->name('dashboard.inventory');
        Route::view('/inventory/analytics', 'dashboard-inventory-analytics')->name('dashboard.inventory.analytics');
        Route::view('/inventory/equipments', 'dashboard-inventory-equipments')->name('dashboard.inventory.equipments');
        Route::view('/inventory/facilities', 'dashboard-inventory-facilities')->name('dashboard.inventory.facilities');
        Route::view('/maintenance', 'dashboard-maintenance')->name('dashboard.maintenance');
        Route::view('/messages', 'dashboard-messages')->name('dashboard.messages');
        Route::view('/profile', 'dashboard-profile')->name('dashboard.profile');
        Route::view('/request', 'dashboard-request')->name('dashboard.request');
        Route::view('/schedule', 'dashboard-schedule')->name('dashboard.schedule');

        // Approval routes for Physical Facilities admin
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('dashboard.approvals');
        Route::patch('/approval/{approvalId}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
        Route::patch('/approval/{approvalId}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
    });
});
