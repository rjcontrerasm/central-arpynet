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
