<?php

use App\Http\Controllers\ContractorContextController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/home', DashboardRedirectController::class)
    ->middleware(['auth', '2fa', 'verified'])
    ->name('home');

Route::middleware(['auth', '2fa', 'verified', 'contractor.module:notifications'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
});

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/contractor/switch', [ContractorContextController::class, 'store'])->name('contractor.switch');
});

Route::redirect('/dashboard', '/home');
