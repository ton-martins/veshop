<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', '2fa', 'verified', 'role:admin'])
    ->prefix('app')
    ->name('admin.')
    ->group(function (): void {
        require __DIR__.'/admin/core.php';
        require __DIR__.'/admin/branding.php';
        require __DIR__.'/admin/storefront.php';
        require __DIR__.'/admin/catalog.php';
        require __DIR__.'/admin/crm.php';
        require __DIR__.'/admin/inventory.php';
        require __DIR__.'/admin/orders.php';
        require __DIR__.'/admin/pdv.php';
        require __DIR__.'/admin/finance.php';
        require __DIR__.'/admin/reports.php';
        require __DIR__.'/admin/services.php';
    });
