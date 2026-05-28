<?php

namespace App\Console\Commands;

use App\Services\TelegramWebhookService;
use Illuminate\Console\Command;

class SetupTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:setup-webhook';

    protected $description = 'Встановити webhook з підтримкою chat_member (трекінг підписок на канал)';

    public function handle(TelegramWebhookService $webhookService): int
    {
        $result = $webhookService->setup();

        $this->info('Webhook встановлено: ' . $result['url']);
        $this->line(json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $webhookService->isSuccessful($result) ? self::SUCCESS : self::FAILURE;
    }
}
