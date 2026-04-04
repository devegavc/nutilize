<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'index')->name('index');

Route::view('/login', 'login')->name('login');
Route::view('/register', 'register')->name('register');

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
});
