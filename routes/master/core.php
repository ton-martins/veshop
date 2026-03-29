<?php

use App\Http\Controllers\Master\ContractorController;
use App\Http\Controllers\Master\DashboardController;
use App\Http\Controllers\Master\PaymentGatewayCatalogController;
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

Route::get('payment-gateways', [PaymentGatewayCatalogController::class, 'index'])
    ->name('payment-gateways.index');
Route::post('payment-gateways', [PaymentGatewayCatalogController::class, 'store'])
    ->name('payment-gateways.store');
Route::put('payment-gateways/{gatewayId}', [PaymentGatewayCatalogController::class, 'update'])
    ->name('payment-gateways.update');
Route::delete('payment-gateways/{gatewayId}', [PaymentGatewayCatalogController::class, 'destroy'])
    ->name('payment-gateways.destroy');
