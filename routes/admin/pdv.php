<?php

use App\Http\Controllers\Admin\PdvController;
use App\Http\Controllers\Admin\SalesController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:pdv')->group(function (): void {
    Route::get('/pdv', [PdvController::class, 'index'])->name('pdv.index');
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::put('/sales/{sale}', [SalesController::class, 'update'])->name('sales.update');
    Route::post('/sales/{sale}/cancel', [SalesController::class, 'cancel'])->name('sales.cancel');
    Route::post('/pdv/cash/open', [PdvController::class, 'openCashSession'])->name('pdv.cash.open');
    Route::post('/pdv/cash/close', [PdvController::class, 'closeCashSession'])->name('pdv.cash.close');
    Route::post('/pdv/sales', [PdvController::class, 'storeSale'])->name('pdv.sales.store');
    Route::put('/pdv/products/featured', [PdvController::class, 'updateFeaturedProducts'])->name('pdv.products.featured.update');
    Route::post('/pdv/clients', [PdvController::class, 'storeClient'])->name('pdv.clients.store');
});
