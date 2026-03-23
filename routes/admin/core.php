<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ManualController;
use Illuminate\Support\Facades\Route;

Route::get('/home', DashboardController::class)->name('home');
Route::redirect('/dashboard', '/app/home');
Route::redirect('/inicio', '/app/home');
Route::get('/manuals', [ManualController::class, 'index'])->name('manuals.index');
