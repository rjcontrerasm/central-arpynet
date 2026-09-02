<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/webhooks/whatsapp',
    [
        WhatsappWebhookController::class,
        'verify',
    ],
)->name('whatsapp.webhook.verify');

Route::post(
    '/webhooks/whatsapp',
    [
        WhatsappWebhookController::class,
        'receive',
    ],
)->name('whatsapp.webhook.receive');
