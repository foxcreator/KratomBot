<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\TelegramChannelTrackingService;
use Illuminate\Console\Command;
use Telegram\Bot\Api;

class SyncChannelSubscriptionsCommand extends Command
{
    protected $signature = 'telegram:sync-channel-subscriptions {--limit=100 : Скільки користувачів обробити за раз}';

    protected $description = 'Оновити статус підписки на канал для користувачів бота (getChatMember)';

    public function handle(TelegramChannelTrackingService $tracking): int
    {
        if (!$tracking->getChannelChatId()) {
            $this->error('telegram_channel_username не налаштовано.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $telegram = new Api(config('telegram.bots.mybot.token'));
        $processed = 0;

        Member::query()
            ->whereNotNull('telegram_id')
            ->orderByDesc('last_interaction_at')
            ->limit($limit)
            ->each(function (Member $member) use ($tracking, $telegram, &$processed) {
                $tracking->syncSubscriptionStatus($member, (string) $member->telegram_id, $telegram);
                $processed++;
                usleep(100_000);
            });

        $this->info("Оновлено {$processed} користувачів.");

        return self::SUCCESS;
    }
}
