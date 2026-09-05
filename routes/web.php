<?php

Route::redirect('/', '/mi-dia', 302)->name('home');


use App\Http\Controllers\GoogleCalendarController;
use Illuminate\Support\Facades\Route;



Route::redirect('/login', '/admin/login', 302)
    ->name('login');


Route::middleware('auth')->group(function (): void {
    Route::get(
        '/google-calendar/connect',
        [GoogleCalendarController::class, 'connect'],
    )->name('google-calendar.connect');

    Route::get(
        '/google-calendar/callback',
        [GoogleCalendarController::class, 'callback'],
    )->name('google-calendar.callback');

    Route::post(
        '/google-calendar/sync',
        [GoogleCalendarController::class, 'sync'],
    )->name('google-calendar.sync');

    Route::post(
        '/google-calendar/disconnect',
        [GoogleCalendarController::class, 'disconnect'],
    )->name('google-calendar.disconnect');
});


Route::middleware('auth')->group(function (): void {
    Route::get(
        '/captura',
        [
            \App\Http\Controllers\QuickCaptureController::class,
            'show',
        ],
    )->name('quick-capture.show');

    Route::post(
        '/captura',
        [
            \App\Http\Controllers\QuickCaptureController::class,
            'store',
        ],
    )->name('quick-capture.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/agenda',
        [\App\Http\Controllers\OperationalAgendaController::class, 'show'],
    )->name('operational-agenda.show');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/mi-dia',
        [
            \App\Http\Controllers\DailyOpsController::class,
            'show',
        ],
    )->name('daily-ops.show');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/mi-dia/tareas/{task}/accion',
        [
            \App\Http\Controllers\DailyTaskActionController::class,
            'update',
        ],
    )->name('daily-task-action.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/deshacer',
        [
            \App\Http\Controllers\GlobalUndoController::class,
            'restore',
        ],
    )->name('global-undo.restore');

    Route::post(
        '/mi-dia/deshacer',
        [
            \App\Http\Controllers\DailyTaskUndoController::class,
            'restore',
        ],
    )->name('daily-task-action.undo');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/mi-dia/tareas/{task}/editar',
        [
            \App\Http\Controllers\DailyTaskEditController::class,
            'update',
        ],
    )->name('daily-task-edit.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/tareas/{task}/cancelar',
        [
            \App\Http\Controllers\TaskLifecycleController::class,
            'cancel',
        ],
    )->name('task-lifecycle.cancel');

    Route::post(
        '/tareas/{task}/eliminar',
        [
            \App\Http\Controllers\TaskLifecycleController::class,
            'delete',
        ],
    )->name('task-lifecycle.delete');

    Route::get(
        '/papelera',
        [
            \App\Http\Controllers\TaskLifecycleController::class,
            'trash',
        ],
    )->name('task-lifecycle.trash');

    Route::post(
        '/papelera/tareas/{taskId}/restaurar',
        [
            \App\Http\Controllers\TaskLifecycleController::class,
            'restore',
        ],
    )->name('task-lifecycle.restore');

    Route::post(
        '/papelera/tareas/{taskId}/eliminar-definitivamente',
        [
            \App\Http\Controllers\TaskLifecycleController::class,
            'purge',
        ],
    )->name('task-lifecycle.purge');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/tareas/{task}/convertir',
        [
            \App\Http\Controllers\TaskConversionController::class,
            'show',
        ],
    )->name('task-conversion.show');

    Route::post(
        '/tareas/{task}/convertir',
        [
            \App\Http\Controllers\TaskConversionController::class,
            'store',
        ],
    )->name('task-conversion.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/tareas/{task}/proxima-accion',
        [
            \App\Http\Controllers\TaskNextActionController::class,
            'update',
        ],
    )->name('task-next-action.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/mi-dia/tareas/{task}/esperar',
        [
            \App\Http\Controllers\DailyTaskWaitingController::class,
            'wait',
        ],
    )->name('daily-task-waiting.wait');

    Route::post(
        '/mi-dia/tareas/{task}/reactivar',
        [
            \App\Http\Controllers\DailyTaskWaitingController::class,
            'resume',
        ],
    )->name('daily-task-waiting.resume');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/servicios',
        [
            \App\Http\Controllers\ServiceOrderOpsController::class,
            'show',
        ],
    )->name('service-orders-ops.show');

    Route::post(
        '/servicios/{serviceOrder}/actualizar',
        [
            \App\Http\Controllers\ServiceOrderOpsActionController::class,
            'update',
        ],
    )->name('service-orders-ops.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post(
        '/servicios/{serviceOrder}/finanzas',
        [
            \App\Http\Controllers\ServiceOrderFinanceController::class,
            'update',
        ],
    )->name('service-orders-finance.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/vencimientos',
        [
            \App\Http\Controllers\ObligationOpsController::class,
            'show',
        ],
    )->name('obligation-ops.show');

    Route::post(
        '/vencimientos/{obligationOccurrence}/actualizar',
        [
            \App\Http\Controllers\ObligationOpsActionController::class,
            'update',
        ],
    )->name('obligation-ops.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/seguimiento',
        [
            \App\Http\Controllers\GlobalTrackingController::class,
            'show',
        ],
    )->name('global-tracking.show');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/revision-semanal',
        [
            \App\Http\Controllers\WeeklyReviewController::class,
            'show',
        ],
    )->name('weekly-review.show');

    Route::post(
        '/revision-semanal/revisar',
        [
            \App\Http\Controllers\WeeklyReviewController::class,
            'mark',
        ],
    )->name('weekly-review.mark');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/revision-diaria',
        [
            \App\Http\Controllers\DailyReviewController::class,
            'show',
        ],
    )->name('daily-review.show');

    Route::post(
        '/revision-diaria/revisar',
        [
            \App\Http\Controllers\DailyReviewController::class,
            'mark',
        ],
    )->name('daily-review.mark');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/decisiones',
        [
            \App\Http\Controllers\DecisionInboxController::class,
            'index',
        ],
    )->name('decision-inbox.index');

    Route::post(
        '/decisiones/tareas/{task}/accion',
        [
            \App\Http\Controllers\DecisionTaskActionController::class,
            'update',
        ],
    )->name('decision-task-action.update');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/resumen',
        [
            \App\Http\Controllers\ExecutiveSummaryController::class,
            'show',
        ],
    )->name('executive-summary.show');
});

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/notificaciones',
        [
            \App\Http\Controllers\NotificationCenterController::class,
            'index',
        ],
    )->name('notification-center.index');

    Route::post(
        '/notificaciones/leer-todas',
        [
            \App\Http\Controllers\NotificationCenterController::class,
            'readAll',
        ],
    )->name('notification-center.read-all');

    Route::post(
        '/notificaciones/{notification}/leer',
        [
            \App\Http\Controllers\NotificationCenterController::class,
            'read',
        ],
    )->name('notification-center.read');
});

Route::middleware('auth')
    ->get(
        '/historial',
        [
            \App\Http\Controllers\AuditHistoryController::class,
            'index',
        ],
    )
    ->name('audit-history.index');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/automatizaciones',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'index',
        ],
    )->name('automation-center.index');

    Route::post(
        '/automatizaciones',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'store',
        ],
    )->name('automation-center.store');

    Route::post(
        '/automatizaciones/{automationRule}/activar',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'toggle',
        ],
    )->name('automation-center.toggle');

    Route::post(
        '/automatizaciones/{automationRule}/vista-previa',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'preview',
        ],
    )->name('automation-center.preview');

    Route::post(
        '/automatizaciones/{automationRule}/ejecutar',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'run',
        ],
    )->name('automation-center.run');

    Route::post(
        '/automatizaciones/ejecuciones/{automationRun}/confirmar',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'confirm',
        ],
    )->name('automation-center.confirm');

    Route::post(
        '/automatizaciones/ejecuciones/{automationRun}/rechazar',
        [
            \App\Http\Controllers\AutomationCenterController::class,
            'reject',
        ],
    )->name('automation-center.reject');
});
