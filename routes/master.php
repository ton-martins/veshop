<?php

use App\Http\Controllers\Master\ContractorController;
use App\Http\Controllers\Master\DashboardController;
use App\Http\Controllers\Master\PlanController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', '2fa', 'verified', 'role:master'])
    ->prefix('master')
    ->name('master.')
    ->group(function (): void {
        Route::get('/home', DashboardController::class)->name('home');
        Route::redirect('/dashboard', '/master/home');
        Route::redirect('/inicio', '/master/home');

        Route::resource('users', UserController::class)
            ->except(['show']);

        Route::resource('contractors', ContractorController::class)
            ->except(['show', 'create', 'edit']);

        Route::resource('plans', PlanController::class)
            ->except(['show', 'create', 'edit']);

        Route::get('/billing', function () {
            return Inertia::render('Master/Billing/Index');
        })->name('billing.index');

        Route::get('/support', function () {
            return Inertia::render('Master/Support/Index');
        })->name('support.index');
    });
