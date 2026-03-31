<?php

use App\Http\Controllers\Admin\CollaboratorController;
use Illuminate\Support\Facades\Route;

Route::middleware('contractor.module:collaborators')->group(function (): void {
    Route::get('/collaborators', [CollaboratorController::class, 'index'])->name('collaborators.index');
    Route::post('/collaborators', [CollaboratorController::class, 'store'])->name('collaborators.store');
    Route::put('/collaborators/{collaborator}', [CollaboratorController::class, 'update'])->name('collaborators.update');
    Route::delete('/collaborators/{collaborator}', [CollaboratorController::class, 'destroy'])->name('collaborators.destroy');
});
