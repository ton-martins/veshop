<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', '2fa', 'verified', 'role:master'])
    ->prefix('master')
    ->name('master.')
    ->group(function (): void {
        require __DIR__.'/master/core.php';
        require __DIR__.'/master/branding.php';
    });
