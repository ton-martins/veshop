<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\ContractorBrandingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ServiceCatalogController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', '2fa', 'verified', 'role:admin'])
    ->prefix('app')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/home', DashboardController::class)->name('home');
        Route::redirect('/dashboard', '/app/home');
        Route::redirect('/inicio', '/app/home');

        Route::get('/branding', [ContractorBrandingController::class, 'edit'])->name('branding.index');
        Route::put('/branding', [ContractorBrandingController::class, 'update'])->name('branding.update');

        Route::middleware('contractor.module:commercial')->group(function (): void {
            Route::resource('products', ProductController::class)
                ->except(['show', 'create', 'edit']);

            Route::resource('categories', CategoryController::class)
                ->except(['show', 'create', 'edit']);

            Route::resource('clients', ClientController::class)
                ->except(['show', 'create', 'edit']);

            Route::resource('suppliers', SupplierController::class)
                ->except(['show', 'create', 'edit']);

            Route::get('/orders', function () {
                return Inertia::render('Admin/Orders/Index');
            })->name('orders.index');

            Route::get('/inventory', function () {
                return Inertia::render('Admin/Inventory/Index');
            })->name('inventory.index');

            Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');

            Route::redirect('/finance/payables', '/app/finance?tab=payables')->name('finance.payables');
            Route::redirect('/finance/receivables', '/app/finance?tab=receivables')->name('finance.receivables');
            Route::redirect('/finance/payments', '/app/finance?tab=payments')->name('finance.payments');
            Route::post('/finance/gateways', [PaymentGatewayController::class, 'store'])->name('finance.gateways.store');
            Route::put('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('finance.gateways.update');
            Route::delete('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('finance.gateways.destroy');
            Route::post('/finance/methods', [PaymentMethodController::class, 'store'])->name('finance.methods.store');
            Route::put('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('finance.methods.update');
            Route::delete('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('finance.methods.destroy');

            Route::get('/reports', function () {
                return Inertia::render('Admin/Reports/Index');
            })->name('reports.index');
        });

        Route::middleware('contractor.module:services')->prefix('services')->name('services.')->group(function (): void {
            Route::get('/', function () {
                return Inertia::render('Admin/Services/Index');
            })->name('index');

            Route::get('/catalog', [ServiceCatalogController::class, 'index'])->name('catalog');

            Route::get('/orders', function () {
                return Inertia::render('Admin/Services/Orders');
            })->name('orders');

            Route::get('/schedule', function () {
                return Inertia::render('Admin/Services/Schedule');
            })->name('schedule');
        });
    });
