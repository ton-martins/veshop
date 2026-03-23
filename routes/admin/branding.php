<?php

use App\Http\Controllers\Admin\ContractorBrandingController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:files')->group(function (): void {
    Route::get('/branding', [ContractorBrandingController::class, 'edit'])->name('branding.index');
    Route::put('/branding', [ContractorBrandingController::class, 'update'])->name('branding.update');
});
