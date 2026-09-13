<?php

use App\Http\Controllers\ClientOpsActionController;
use App\Http\Controllers\ClientOpsController;
use App\Http\Controllers\ProjectFrontActionController;
use App\Http\Controllers\ProjectFrontController;
use App\Http\Controllers\ServiceOrderFrontActionController;
use App\Http\Controllers\ServiceOrderFrontController;
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

    Route::get(
        '/proyectos/nuevo',
        [ProjectFrontController::class, 'create'],
    )->name('project-front.create');

    Route::post(
        '/proyectos',
        [ProjectFrontActionController::class, 'store'],
    )->name('project-front.store');

    Route::get(
        '/proyectos/{project}/editar',
        [ProjectFrontController::class, 'edit'],
    )->name('project-front.edit');

    Route::post(
        '/proyectos/{project}/editar',
        [ProjectFrontActionController::class, 'update'],
    )->name('project-front.update');

    Route::get(
        '/servicios/nuevo',
        [ServiceOrderFrontController::class, 'create'],
    )->name('service-order-front.create');

    Route::post(
        '/servicios',
        [ServiceOrderFrontActionController::class, 'store'],
    )->name('service-order-front.store');

    Route::get(
        '/servicios/{serviceOrder}/editar',
        [ServiceOrderFrontController::class, 'edit'],
    )->name('service-order-front.edit');

    Route::post(
        '/servicios/{serviceOrder}/editar',
        [ServiceOrderFrontActionController::class, 'update'],
    )->name('service-order-front.update');
});
