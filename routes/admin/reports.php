<?php

use App\Http\Controllers\Admin\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:reports')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/exports', [ReportController::class, 'export'])
        ->name('reports.exports');
    Route::post('/reports/exports/sales', [ReportController::class, 'exportSales'])
        ->name('reports.exports.sales');
    Route::get('/reports/exports/{reportExport}/download', [ReportController::class, 'download'])
        ->name('reports.exports.download');
});
