<?php

use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('contractor.module:inventory')->group(function (): void {
    Route::resource('suppliers', SupplierController::class)
        ->except(['show', 'create', 'edit']);

    Route::get('/inventory', function () {
        return Inertia::render('Admin/Inventory/Index');
    })->name('inventory.index');
});
