<?php

namespace App\Services;

use Telegram\Bot\Api;

class TelegramWebhookService
{
    /**
     * @return array{url: string, response: array<string, mixed>}
     */
    public function setup(?Api $telegram = null): array
    {
        $url = rtrim((string) config('app.url'), '/') . '/telegram/webhook';
        $telegram ??= new Api(config('telegram.bots.mybot.token'));

        $response = $telegram->setWebhook([
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'chat_member'],
        ]);

        return [
            'url' => $url,
            'response' => $this->normalizeResponse($response),
        ];
    }

    public function isSuccessful(array $result): bool
    {
        return ($result['response']['ok'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        return ['ok' => false, 'description' => 'Unknown response'];
    }
}
