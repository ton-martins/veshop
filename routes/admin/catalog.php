<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:catalog')->group(function (): void {
    Route::resource('products', ProductController::class)
        ->except(['show', 'create', 'edit']);

    Route::resource('categories', CategoryController::class)
        ->except(['show', 'create', 'edit']);
});
