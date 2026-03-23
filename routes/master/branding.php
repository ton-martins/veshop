<?php

use App\Http\Controllers\Master\BrandingController;
use Illuminate\Support\Facades\Route;

Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.index');
Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
