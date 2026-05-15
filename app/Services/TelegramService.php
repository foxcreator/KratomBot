<?php

namespace App\Services;

use App\Models\Member;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramService
{
    public function sendMessage($telegramId, string $message, ?string $parseMode = null)
    {
        $params = [
            'chat_id' => $telegramId,
            'text' => $message,
        ];

        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }

        return Telegram::sendMessage($params);
    }

    public function sendPhoto($telegramId, string $photoPath, ?string $caption = null, ?string $parseMode = null)
    {
        $params = [
            'chat_id' => $telegramId,
            'photo' => InputFile::create($photoPath, basename($photoPath)),
        ];

        if (!is_null($caption) && $caption !== '') {
            $params['caption'] = $caption;
            if ($parseMode) {
                $params['parse_mode'] = $parseMode;
            }
        }

        return Telegram::sendPhoto($params);
    }
}
