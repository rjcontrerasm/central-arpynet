<?php

use App\Http\Controllers\CentralCopilotController;
use App\Http\Controllers\ClientOpsActionController;
use App\Http\Controllers\ClientOpsController;
use App\Http\Controllers\ProjectFrontActionController;
use App\Http\Controllers\ProjectFrontController;
use App\Http\Controllers\RecurringObligationFrontActionController;
use App\Http\Controllers\RecurringObligationFrontController;
use App\Http\Controllers\RecurringTaskFrontActionController;
use App\Http\Controllers\RecurringTaskFrontController;
use App\Http\Controllers\SafetyRecoveryController;
use App\Http\Controllers\ServiceOrderExecutionOrderController;
use App\Http\Controllers\ServiceOrderFrontActionController;
use App\Http\Controllers\ServiceOrderFrontController;
use App\Http\Controllers\ServiceOrderInvoiceController;
use App\Http\Controllers\ServiceOrderMilestoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/copilot', [CentralCopilotController::class, 'index'])
        ->name('central-copilot.index');

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
    Route::get('/servicios/{serviceOrder}', [ServiceOrderFrontController::class, 'show'])
        ->name('service-order-front.show');
    Route::get('/servicios/{serviceOrder}/editar', [ServiceOrderFrontController::class, 'edit'])
        ->name('service-order-front.edit');
    Route::post('/servicios/{serviceOrder}/editar', [ServiceOrderFrontActionController::class, 'update'])
        ->name('service-order-front.update');

    Route::post('/servicios/{serviceOrder}/hitos', [ServiceOrderMilestoneController::class, 'store'])
        ->name('service-order-milestones.store');
    Route::post('/servicios/{serviceOrder}/hitos/{milestone}', [ServiceOrderMilestoneController::class, 'update'])
        ->name('service-order-milestones.update');

    Route::post('/servicios/{serviceOrder}/hitos/{milestone}/accion', [ServiceOrderMilestoneController::class, 'action'])
        ->name('service-order-milestones.action');

    Route::post('/servicios/{serviceOrder}/ordenes-ejecucion', [ServiceOrderExecutionOrderController::class, 'store'])
        ->name('service-order-execution-orders.store');
    Route::post('/servicios/{serviceOrder}/ordenes-ejecucion/{executionOrder}', [ServiceOrderExecutionOrderController::class, 'update'])
        ->name('service-order-execution-orders.update');

    Route::post('/servicios/{serviceOrder}/facturas', [ServiceOrderInvoiceController::class, 'store'])
        ->name('service-order-invoices.store');
    Route::post('/servicios/{serviceOrder}/facturas/{invoice}', [ServiceOrderInvoiceController::class, 'update'])
        ->name('service-order-invoices.update');

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
