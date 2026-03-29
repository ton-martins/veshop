<?php

use App\Http\Controllers\Admin\AccessAuditController;
use Illuminate\Support\Facades\Route;

Route::get('/audit/accesses', [AccessAuditController::class, 'index'])
    ->name('audit.accesses');

Route::post('/audit/accesses/disconnect-all', [AccessAuditController::class, 'disconnectAll'])
    ->name('audit.accesses.disconnect-all');

