<?php

use App\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:orders')->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::put('/orders/{sale}', [OrderController::class, 'update'])->name('orders.update');
    Route::post('/orders/{sale}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('/orders/{sale}/reject', [OrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{sale}/paid', [OrderController::class, 'markAsPaid'])->name('orders.paid');
    Route::post('/orders/{sale}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});
