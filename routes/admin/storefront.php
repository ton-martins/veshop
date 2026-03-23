<?php

use App\Http\Controllers\Admin\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:checkout,services_storefront')->group(function (): void {
    Route::get('/storefront', [StorefrontController::class, 'edit'])->name('storefront.index');
    Route::put('/storefront', [StorefrontController::class, 'update'])->name('storefront.update');
});
