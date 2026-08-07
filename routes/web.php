<?php

use App\Http\Controllers\GoogleCalendarController;
use Illuminate\Support\Facades\Route;



Route::redirect('/login', '/admin/login', 302)
    ->name('login');

Route::redirect('/', '/admin', 302)->name('home');

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
        '/google-calendar/disconnect',
        [GoogleCalendarController::class, 'disconnect'],
    )->name('google-calendar.disconnect');
});

