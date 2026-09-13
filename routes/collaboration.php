<?php

use App\Http\Controllers\CollaborationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/colaboracion',
        [CollaborationController::class, 'index'],
    )->name('collaboration.index');

    Route::get(
        '/colaboracion/{type}/{id}',
        [CollaborationController::class, 'thread'],
    )->whereIn('type', [
        'task',
        'project',
        'service_order',
        'incident',
    ])->name('collaboration.thread');

    Route::post(
        '/colaboracion/{type}/{id}',
        [CollaborationController::class, 'store'],
    )->whereIn('type', [
        'task',
        'project',
        'service_order',
        'incident',
    ])->name('collaboration.store');
});
