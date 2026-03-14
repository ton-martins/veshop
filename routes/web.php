<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContractorContextController;
use App\Http\Controllers\DashboardRedirectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Public/Landing', [
        'canLogin' => Route::has('login'),
    ]);
});

Route::get('/home', DashboardRedirectController::class)
    ->middleware(['auth', '2fa', 'verified'])
    ->name('home');

Route::redirect('/dashboard', '/home');

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/contractor/switch', [ContractorContextController::class, 'store'])->name('contractor.switch');
});

require __DIR__.'/master.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
