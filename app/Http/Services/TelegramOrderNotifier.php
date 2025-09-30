<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;

class TelegramOrderNotifier
{
    protected string $botToken;
    protected string $chatId;

    public function __construct()
    {
        $this->botToken = '7626235994:AAHhZOotdkoS5sH9orUTJcg7tZyMDc5OXws';
        $this->chatId = '-1003024012905';
    }

    public function send(string $message): bool
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        $response = Http::post($url, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful();
    }
}
