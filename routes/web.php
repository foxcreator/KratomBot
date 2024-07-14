<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/setwebhook', [TelegramController::class, 'setWebhook']);

Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])->withoutMiddleware(['csrf']);
