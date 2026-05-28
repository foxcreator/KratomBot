<?php

namespace App\Console\Commands;

use App\Services\TelegramChannelTrackingService;
use Illuminate\Console\Command;

class EnsureBotChannelInviteLinkCommand extends Command
{
    protected $signature = 'telegram:ensure-channel-invite-link';

    protected $description = 'Створити invite link для каналу (трекінг підписок через бота)';

    public function handle(TelegramChannelTrackingService $tracking): int
    {
        $channel = $tracking->getChannelChatId();
        if (!$channel) {
            $this->error('Спочатку вкажіть telegram_channel_username або telegram_channel_chat_id в налаштуваннях бота (/admin → Налаштування бота).');

            return self::FAILURE;
        }

        $link = $tracking->ensureBotInviteLink();
        if (!$link) {
            $this->error('Не вдалося створити invite link. Переконайтесь, що бот — адмін каналу з правом запрошувати користувачів.');

            return self::FAILURE;
        }

        $this->info('Invite link створено/оновлено:');
        $this->line($link);

        return self::SUCCESS;
    }
}
