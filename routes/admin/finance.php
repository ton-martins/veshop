<?php

use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FinancialEntryController;
use App\Http\Controllers\Admin\MercadoPagoOAuthController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:finance')->group(function (): void {
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/finance/entries', [FinancialEntryController::class, 'store'])->name('finance.entries.store');
    Route::post('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'update'])->name('finance.entries.update');
    Route::delete('/finance/entries/{financialEntry}', [FinancialEntryController::class, 'destroy'])->name('finance.entries.destroy');

    Route::redirect('/finance/payables', '/app/finance?tab=payables')->name('finance.payables');
    Route::redirect('/finance/receivables', '/app/finance?tab=receivables')->name('finance.receivables');
    Route::get('/finance/payments', [FinanceController::class, 'payments'])->name('finance.payments');
    Route::get('/finance/mercadopago/connect', [MercadoPagoOAuthController::class, 'redirect'])->name('finance.mercadopago.redirect');
    Route::get('/finance/mercadopago/callback', [MercadoPagoOAuthController::class, 'callback'])->name('finance.mercadopago.callback');
    Route::delete('/finance/mercadopago/disconnect', [MercadoPagoOAuthController::class, 'disconnect'])->name('finance.mercadopago.disconnect');
    Route::post('/finance/gateways/test-connection', [PaymentGatewayController::class, 'testConnection'])->name('finance.gateways.test');
    Route::post('/finance/gateways', [PaymentGatewayController::class, 'store'])->name('finance.gateways.store');
    Route::put('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('finance.gateways.update');
    Route::delete('/finance/gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('finance.gateways.destroy');
    Route::post('/finance/methods', [PaymentMethodController::class, 'store'])->name('finance.methods.store');
    Route::post('/finance/methods/mercado-pago/sync', [PaymentMethodController::class, 'syncMercadoPago'])->name('finance.methods.sync-mercado-pago');
    Route::put('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('finance.methods.update');
    Route::delete('/finance/methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('finance.methods.destroy');
});
