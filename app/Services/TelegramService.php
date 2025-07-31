<?php

namespace App\Services;

use App\Models\Member;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramService
{
    public function sendMessage($telegramId, string $message)
    {
        return Telegram::sendMessage([
            'chat_id' => $telegramId,
            'text' => $message
        ]);
    }
}
