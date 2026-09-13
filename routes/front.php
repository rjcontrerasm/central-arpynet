<?php

use App\Http\Controllers\ClientOpsActionController;
use App\Http\Controllers\ClientOpsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/clientes',
        [ClientOpsController::class, 'index'],
    )->name('client-ops.index');

    Route::post(
        '/clientes',
        [ClientOpsActionController::class, 'store'],
    )->name('client-ops.store');

    Route::post(
        '/clientes/{client}/actualizar',
        [ClientOpsActionController::class, 'update'],
    )->name('client-ops.update');
});
