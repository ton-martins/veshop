<?php

use App\Http\Controllers\Master\ContractorController;
use App\Http\Controllers\Master\DashboardController;
use App\Http\Controllers\Master\PlanController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/home', DashboardController::class)->name('home');
Route::redirect('/dashboard', '/master/home');
Route::redirect('/inicio', '/master/home');

Route::resource('users', UserController::class)
    ->except(['show', 'create', 'edit']);

Route::resource('contractors', ContractorController::class)
    ->except(['show', 'create', 'edit']);

Route::put('plans/bulk-update', [PlanController::class, 'bulkUpdate'])
    ->name('plans.bulk-update');

Route::resource('plans', PlanController::class)
    ->except(['show', 'create', 'edit']);
