<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SetupTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:setup-webhook';

    protected $description = 'Встановити webhook з підтримкою chat_member (трекінг підписок на канал)';

    public function handle(): int
    {
        $url = rtrim(config('app.url'), '/') . '/telegram/webhook';
        $telegram = new Api(config('telegram.bots.mybot.token'));

        $response = $telegram->setWebhook([
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'chat_member'],
        ]);

        $this->info('Webhook встановлено: ' . $url);
        $this->line(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
