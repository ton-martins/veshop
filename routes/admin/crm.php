<?php

use App\Http\Controllers\Admin\ClientController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:crm,orders,pdv,services')->group(function (): void {
    Route::resource('clients', ClientController::class)
        ->except(['show', 'create', 'edit']);
});
