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
        '/mi-dia/tareas/{task}/editar',
        [
            \App\Http\Controllers\DailyTaskEditController::class,
            'update',
        ],
    )->name('daily-task-edit.update');
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
