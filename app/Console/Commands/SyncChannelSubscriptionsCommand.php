<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\TelegramChannelTrackingService;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SyncChannelSubscriptionsCommand extends Command
{
    protected $signature = 'telegram:sync-channel-subscriptions {--limit= : Скільки користувачів обробити (порожньо = всіх)} {--chunk=200 : Розмір чанку}';

    protected $description = 'Оновити статус підписки на канал для користувачів бота (getChatMember)';

    public function handle(TelegramChannelTrackingService $tracking): int
    {
        if (!$tracking->getChannelChatId()) {
            $this->error('telegram_channel_username або telegram_channel_chat_id не налаштовано.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(0, (int) $limit) : null;
        $chunkSize = max(50, (int) $this->option('chunk'));
        $telegram = new Api(config('telegram.bots.mybot.token'));
        $processed = 0;

        $query = Member::query()
            ->whereNotNull('telegram_id')
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $query->chunkById($chunkSize, function ($members) use ($tracking, $telegram, &$processed, $limit) {
            foreach ($members as $member) {
                if ($limit !== null && $limit > 0 && $processed >= $limit) {
                    return false;
                }

                $tracking->syncSubscriptionStatus($member, (string) $member->telegram_id, $telegram);
                $processed++;
                usleep(75_000);
            }
        });

        $this->info("Оновлено {$processed} користувачів.");

        return self::SUCCESS;
    }
}
