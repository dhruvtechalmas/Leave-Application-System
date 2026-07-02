<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('leave', 'leave.index')->name('leave.index');

    Route::middleware('super.admin')->group(function () {
        Route::get('admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::view('admin/users', 'admin.users')->name('admin.users');
        Route::view('admin/leave-types', 'admin.leave-types')->name('admin.leave-types');
        Route::view('admin/leave-balances', 'admin.leave-balances')->name('admin.leave-balances');
        Route::view('admin/approvals', 'admin.approvals')->name('admin.approvals');
    });
});
        
require __DIR__.'/settings.php';
