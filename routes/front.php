<?php

use App\Http\Controllers\ClientOpsActionController;
use App\Http\Controllers\ClientOpsController;
use App\Http\Controllers\ProjectFrontActionController;
use App\Http\Controllers\ProjectFrontController;
use App\Http\Controllers\RecurringObligationFrontActionController;
use App\Http\Controllers\RecurringObligationFrontController;
use App\Http\Controllers\RecurringTaskFrontActionController;
use App\Http\Controllers\RecurringTaskFrontController;
use App\Http\Controllers\SafetyRecoveryController;
use App\Http\Controllers\ServiceOrderFrontActionController;
use App\Http\Controllers\ServiceOrderFrontController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/estado-recuperacion', [SafetyRecoveryController::class, 'index'])
        ->name('safety-recovery.index');

    Route::get('/clientes', [ClientOpsController::class, 'index'])
        ->name('client-ops.index');
    Route::post('/clientes', [ClientOpsActionController::class, 'store'])
        ->name('client-ops.store');
    Route::post('/clientes/{client}/actualizar', [ClientOpsActionController::class, 'update'])
        ->name('client-ops.update');

    Route::get('/proyectos/nuevo', [ProjectFrontController::class, 'create'])
        ->name('project-front.create');
    Route::post('/proyectos', [ProjectFrontActionController::class, 'store'])
        ->name('project-front.store');
    Route::get('/proyectos/{project}/editar', [ProjectFrontController::class, 'edit'])
        ->name('project-front.edit');
    Route::post('/proyectos/{project}/editar', [ProjectFrontActionController::class, 'update'])
        ->name('project-front.update');

    Route::get('/servicios/nuevo', [ServiceOrderFrontController::class, 'create'])
        ->name('service-order-front.create');
    Route::post('/servicios', [ServiceOrderFrontActionController::class, 'store'])
        ->name('service-order-front.store');
    Route::get('/servicios/{serviceOrder}/editar', [ServiceOrderFrontController::class, 'edit'])
        ->name('service-order-front.edit');
    Route::post('/servicios/{serviceOrder}/editar', [ServiceOrderFrontActionController::class, 'update'])
        ->name('service-order-front.update');

    Route::get('/vencimientos/recurrentes', [RecurringObligationFrontController::class, 'index'])
        ->name('recurring-obligation-front.index');
    Route::get('/vencimientos/recurrentes/nueva', [RecurringObligationFrontController::class, 'create'])
        ->name('recurring-obligation-front.create');
    Route::post('/vencimientos/recurrentes', [RecurringObligationFrontActionController::class, 'store'])
        ->name('recurring-obligation-front.store');
    Route::get('/vencimientos/recurrentes/{recurringObligation}/editar', [RecurringObligationFrontController::class, 'edit'])
        ->name('recurring-obligation-front.edit');
    Route::post('/vencimientos/recurrentes/{recurringObligation}/editar', [RecurringObligationFrontActionController::class, 'update'])
        ->name('recurring-obligation-front.update');

    Route::get('/tareas-recurrentes', [RecurringTaskFrontController::class, 'index'])
        ->name('recurring-task-front.index');
    Route::get('/tareas-recurrentes/nueva', [RecurringTaskFrontController::class, 'create'])
        ->name('recurring-task-front.create');
    Route::post('/tareas-recurrentes', [RecurringTaskFrontActionController::class, 'store'])
        ->name('recurring-task-front.store');
    Route::get('/tareas-recurrentes/{recurringTaskRule}/editar', [RecurringTaskFrontController::class, 'edit'])
        ->name('recurring-task-front.edit');
    Route::post('/tareas-recurrentes/{recurringTaskRule}/editar', [RecurringTaskFrontActionController::class, 'update'])
        ->name('recurring-task-front.update');
});
