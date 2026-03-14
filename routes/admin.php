<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ContractorBrandingController;
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
            Route::get('/products', function () {
                return Inertia::render('Admin/Products/Index');
            })->name('products.index');

            Route::get('/categories', function () {
                return Inertia::render('Admin/Categories/Index');
            })->name('categories.index');

            Route::get('/clients', function () {
                return Inertia::render('Admin/Clients/Index');
            })->name('clients.index');

            Route::get('/suppliers', function () {
                return Inertia::render('Admin/Suppliers/Index');
            })->name('suppliers.index');

            Route::get('/orders', function () {
                return Inertia::render('Admin/Orders/Index');
            })->name('orders.index');

            Route::get('/inventory', function () {
                return Inertia::render('Admin/Inventory/Index');
            })->name('inventory.index');

            Route::get('/finance/payables', function () {
                return Inertia::render('Admin/Finance/Payables');
            })->name('finance.payables');

            Route::get('/finance/receivables', function () {
                return Inertia::render('Admin/Finance/Receivables');
            })->name('finance.receivables');

            Route::get('/reports', function () {
                return Inertia::render('Admin/Reports/Index');
            })->name('reports.index');
        });

        Route::middleware('contractor.module:services')->prefix('services')->name('services.')->group(function (): void {
            Route::get('/', function () {
                return Inertia::render('Admin/Services/Index');
            })->name('index');

            Route::get('/catalog', function () {
                return Inertia::render('Admin/Services/Catalog');
            })->name('catalog');

            Route::get('/orders', function () {
                return Inertia::render('Admin/Services/Orders');
            })->name('orders');

            Route::get('/schedule', function () {
                return Inertia::render('Admin/Services/Schedule');
            })->name('schedule');
        });
    });
