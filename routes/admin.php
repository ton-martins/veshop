<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancialEntryController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ManualController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\SalesController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PdvController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ContractorBrandingController;
use App\Http\Controllers\Admin\StorefrontController;
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
        Route::get('/manuals', [ManualController::class, 'index'])->name('manuals.index');

        Route::middleware('contractor.module:files')->group(function (): void {
            Route::get('/branding', [ContractorBrandingController::class, 'edit'])->name('branding.index');
            Route::put('/branding', [ContractorBrandingController::class, 'update'])->name('branding.update');
        });
        Route::middleware('contractor.module:checkout')->group(function (): void {
            Route::get('/storefront', [StorefrontController::class, 'edit'])->name('storefront.index');
            Route::put('/storefront', [StorefrontController::class, 'update'])->name('storefront.update');
        });

        Route::middleware('contractor.module:catalog')->group(function (): void {
            Route::resource('products', ProductController::class)
                ->except(['show', 'create', 'edit']);

            Route::resource('categories', CategoryController::class)
                ->except(['show', 'create', 'edit']);
        });

        Route::middleware('contractor.module:crm,orders,pdv')->group(function (): void {
            Route::resource('clients', ClientController::class)
                ->except(['show', 'create', 'edit']);
        });

        Route::middleware('contractor.module:inventory')->group(function (): void {
            Route::resource('suppliers', SupplierController::class)
                ->except(['show', 'create', 'edit']);

            Route::get('/inventory', function () {
                return Inertia::render('Admin/Inventory/Index');
            })->name('inventory.index');
        });

        Route::middleware('contractor.module:orders')->group(function (): void {
            Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
            Route::put('/orders/{sale}', [OrderController::class, 'update'])->name('orders.update');
            Route::post('/orders/{sale}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('/orders/{sale}/reject', [OrderController::class, 'reject'])->name('orders.reject');
            Route::post('/orders/{sale}/paid', [OrderController::class, 'markAsPaid'])->name('orders.paid');
            Route::post('/orders/{sale}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        });

        Route::middleware('contractor.module:pdv')->group(function (): void {
            Route::get('/pdv', [PdvController::class, 'index'])->name('pdv.index');
            Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
            Route::put('/sales/{sale}', [SalesController::class, 'update'])->name('sales.update');
            Route::post('/pdv/cash/open', [PdvController::class, 'openCashSession'])->name('pdv.cash.open');
            Route::post('/pdv/cash/close', [PdvController::class, 'closeCashSession'])->name('pdv.cash.close');
            Route::post('/pdv/sales', [PdvController::class, 'storeSale'])->name('pdv.sales.store');
            Route::put('/pdv/products/featured', [PdvController::class, 'updateFeaturedProducts'])->name('pdv.products.featured.update');
            Route::post('/pdv/clients', [PdvController::class, 'storeClient'])->name('pdv.clients.store');
        });

        Route::middleware('contractor.module:finance')->group(function (): void {
            Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::post('/finance/entries', [FinancialEntryController::class, 'store'])->name('finance.entries.store');
            Route::post('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'update'])->name('finance.entries.update');
            Route::delete('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'destroy'])->name('finance.entries.destroy');

            Route::redirect('/finance/payables', '/app/finance?tab=payables')->name('finance.payables');
            Route::redirect('/finance/receivables', '/app/finance?tab=receivables')->name('finance.receivables');
            Route::redirect('/finance/payments', '/app/finance?tab=payments')->name('finance.payments');
            Route::post('/finance/gateways/test-connection', [PaymentGatewayController::class, 'testConnection'])->name('finance.gateways.test');
            Route::post('/finance/gateways', [PaymentGatewayController::class, 'store'])->name('finance.gateways.store');
            Route::put('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('finance.gateways.update');
            Route::delete('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('finance.gateways.destroy');
            Route::post('/finance/methods', [PaymentMethodController::class, 'store'])->name('finance.methods.store');
            Route::put('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('finance.methods.update');
            Route::delete('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('finance.methods.destroy');
        });

        Route::middleware('contractor.module:reports')->group(function (): void {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::post('/reports/exports/sales', [ReportController::class, 'exportSales'])
                ->name('reports.exports.sales');
            Route::get('/reports/exports/{reportExport}/download', [ReportController::class, 'download'])
                ->name('reports.exports.download');
        });

        Route::middleware('contractor.module:services')->prefix('services')->name('services.')->group(function (): void {
            Route::get('/', function () {
                return Inertia::render('Admin/Services/Index');
            })->name('index');

            Route::middleware('contractor.module:services_catalog')->get('/catalog', [ServiceCatalogController::class, 'index'])->name('catalog');

            Route::middleware('contractor.module:service_orders')->get('/orders', function () {
                return Inertia::render('Admin/Services/Orders');
            })->name('orders');

            Route::middleware('contractor.module:schedule')->get('/schedule', function () {
                return Inertia::render('Admin/Services/Schedule');
            })->name('schedule');
        });
    });
