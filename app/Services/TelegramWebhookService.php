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
        $response = $result['response'] ?? [];

        if (($response['ok'] ?? false) === true) {
            return true;
        }

        return ($response['result'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (! is_object($response)) {
            return ['ok' => false, 'description' => 'Unknown response'];
        }

        if (method_exists($response, 'toArray')) {
            $data = $response->toArray();

            if (is_array($data)) {
                return $data;
            }
        }

        if (method_exists($response, 'getDecodedBody')) {
            $data = $response->getDecodedBody();

            if (is_array($data)) {
                return $data;
            }
        }

        if ($response instanceof \JsonSerializable) {
            $data = $response->jsonSerialize();

            if (is_array($data)) {
                return $data;
            }
        }

        if (method_exists($response, '__toString')) {
            $decoded = json_decode((string) $response, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['ok' => false, 'description' => 'Unknown response'];
    }
}
