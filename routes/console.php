<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Синк підписок — раз на 15 хвилин.
// Щохвилини = постійне навантаження на Telegram API → timeout'и в webhook → chat_member events гинуть.
// Реальний стан підписки відстежується через chat_member webhook (миттєво після підписки).
// Синк — тільки страховка якщо webhook event пропустив.
Schedule::command('telegram:sync-channel-subscriptions')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
