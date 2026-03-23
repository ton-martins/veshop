<?php

use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\ServiceCatalogController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceOrderController;
use App\Http\Controllers\Admin\ServiceScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:services')->prefix('services')->name('services.')->group(function (): void {
    Route::get('/', static function () {
        return redirect()->route('admin.home');
    })->name('index');

    Route::middleware('contractor.module:services_catalog,services')->group(function (): void {
        Route::get('/categories', [ServiceCategoryController::class, 'index'])->name('categories');
        Route::post('/categories', [ServiceCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{serviceCategory}', [ServiceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{serviceCategory}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('/catalog', [ServiceCatalogController::class, 'index'])->name('catalog');
        Route::post('/catalog', [ServiceCatalogController::class, 'store'])->name('catalog.store');
        Route::put('/catalog/{serviceCatalog}', [ServiceCatalogController::class, 'update'])->name('catalog.update');
        Route::delete('/catalog/{serviceCatalog}', [ServiceCatalogController::class, 'destroy'])->name('catalog.destroy');
    });

    Route::middleware('contractor.module:service_orders')->group(function (): void {
        Route::get('/orders', [ServiceOrderController::class, 'index'])->name('orders');
        Route::post('/orders', [ServiceOrderController::class, 'store'])->name('orders.store');
        Route::put('/orders/{serviceOrder}', [ServiceOrderController::class, 'update'])->name('orders.update');
        Route::delete('/orders/{serviceOrder}', [ServiceOrderController::class, 'destroy'])->name('orders.destroy');
    });

    Route::middleware('contractor.module:schedule')->group(function (): void {
        Route::get('/pdv', [ServiceScheduleController::class, 'index'])->name('pdv');
        Route::get('/schedule', [ServiceScheduleController::class, 'index'])->name('schedule');
        Route::post('/schedule', [ServiceScheduleController::class, 'store'])->name('schedule.store');
        Route::put('/schedule/{serviceAppointment}', [ServiceScheduleController::class, 'update'])->name('schedule.update');
        Route::delete('/schedule/{serviceAppointment}', [ServiceScheduleController::class, 'destroy'])->name('schedule.destroy');
    });

    Route::middleware('contractor.module:tasks')->group(function (): void {
        Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting');
    });

    Route::middleware('contractor.module:finance')->group(function (): void {
        Route::post('/accounting/fees', [AccountingController::class, 'storeFee'])->name('accounting.fees.store');
        Route::put('/accounting/fees/{accountingFeeEntry}', [AccountingController::class, 'updateFee'])->name('accounting.fees.update');
        Route::delete('/accounting/fees/{accountingFeeEntry}', [AccountingController::class, 'destroyFee'])->name('accounting.fees.destroy');
    });

    Route::middleware('contractor.module:tasks')->group(function (): void {
        Route::post('/accounting/obligations', [AccountingController::class, 'storeObligation'])->name('accounting.obligations.store');
        Route::put('/accounting/obligations/{accountingObligation}', [AccountingController::class, 'updateObligation'])->name('accounting.obligations.update');
        Route::delete('/accounting/obligations/{accountingObligation}', [AccountingController::class, 'destroyObligation'])->name('accounting.obligations.destroy');
        Route::post('/accounting/client-profiles', [AccountingController::class, 'storeClientProfile'])->name('accounting.client-profiles.store');
        Route::put('/accounting/client-profiles/{client}', [AccountingController::class, 'updateClientProfile'])->name('accounting.client-profiles.update');
        Route::delete('/accounting/client-profiles/{client}', [AccountingController::class, 'destroyClientProfile'])->name('accounting.client-profiles.destroy');
        Route::post('/accounting/templates', [AccountingController::class, 'storeTemplate'])->name('accounting.templates.store');
        Route::put('/accounting/templates/{accountingServiceTemplate}', [AccountingController::class, 'updateTemplate'])->name('accounting.templates.update');
        Route::delete('/accounting/templates/{accountingServiceTemplate}', [AccountingController::class, 'destroyTemplate'])->name('accounting.templates.destroy');
        Route::post('/accounting/automation/recurring-fees', [AccountingController::class, 'processRecurringFees'])->name('accounting.automation.recurring-fees');
        Route::post('/accounting/automation/reminders', [AccountingController::class, 'processReminders'])->name('accounting.automation.reminders');
    });

    Route::middleware('contractor.module:documents')->group(function (): void {
        Route::post('/accounting/documents', [AccountingController::class, 'storeDocument'])->name('accounting.documents.store');
        Route::put('/accounting/documents/{accountingDocumentRequest}', [AccountingController::class, 'updateDocument'])->name('accounting.documents.update');
        Route::delete('/accounting/documents/{accountingDocumentRequest}', [AccountingController::class, 'destroyDocument'])->name('accounting.documents.destroy');
        Route::post('/accounting/documents/{accountingDocumentRequest}/versions', [AccountingController::class, 'storeDocumentVersion'])->name('accounting.documents.versions.store');
    });
});
