<?php

namespace App\Services;

use App\Models\ChannelSubscriptionEvent;
use App\Models\Member;
use App\Settings\TelegramSettings;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramChannelTrackingService
{
    public const INVITE_LINK_NAME = 'kratom_bot_tracking';
    private const EVENT_DEDUP_MINUTES = 10;

    public function __construct(
        protected TelegramSettings $settings,
    ) {}

    public function ensureBotInviteLink(?Api $telegram = null): ?string
    {
        if (filled($this->settings->bot_channel_invite_link)) {
            return $this->settings->bot_channel_invite_link;
        }

        $channelId = $this->getChannelChatId();
        if (!$channelId) {
            Log::warning('[ChannelTracking] Не вказано telegram_channel_chat_id або telegram_channel_username');

            return null;
        }

        $telegram ??= new Api(config('telegram.bots.mybot.token'));

        try {
            $response = $telegram->createChatInviteLink([
                'chat_id' => $channelId,
                'name' => self::INVITE_LINK_NAME,
            ]);

            $link = is_array($response)
                ? ($response['invite_link'] ?? null)
                : ($response->inviteLink ?? $response->invite_link ?? null);

            if (!$link) {
                Log::error('[ChannelTracking] createChatInviteLink не повернув посилання', [
                    'response' => $response,
                ]);

                return null;
            }

            $this->settings->bot_channel_invite_link = $link;
            $this->settings->save();

            Log::info('[ChannelTracking] Створено invite link для трекінгу', ['link' => $link]);

            return $link;
        } catch (\Throwable $e) {
            Log::error('[ChannelTracking] Помилка createChatInviteLink: ' . $e->getMessage());

            return null;
        }
    }

    public function handleChatMemberUpdate(Update $update): void
    {
        $chatMemberUpdate = $update->getChatMember();
        if (!$chatMemberUpdate) {
            Log::warning('[ChannelTracking] getChatMember() повернув null');
            return;
        }

        // SDK magic-getters (.getStatus(), .getId() тощо) повертають false/null
        // через баг у __call BaseObject. Читаємо всі дані через toArray() — надійно і без магії.
        $raw = method_exists($chatMemberUpdate, 'toArray') ? $chatMemberUpdate->toArray() : [];

        $channelId = $this->getChannelChatId();
        if (!$channelId) {
            return;
        }

        $chatId       = (string) ($raw['chat']['id'] ?? '');
        $chatUsername = $raw['chat']['username'] ?? null;
        if (!$this->isTargetChannelRaw($chatId, $chatUsername, $channelId)) {
            return;
        }

        $newStatus  = $raw['new_chat_member']['status'] ?? null;
        $oldStatus  = $raw['old_chat_member']['status'] ?? null;
        $telegramId = (string) ($raw['new_chat_member']['user']['id'] ?? '');

        if (!$telegramId) {
            Log::warning('[ChannelTracking] telegramId пустий у chat_member update');
            return;
        }

        // Оновлюємо тільки тих, хто вже стартував бота. Нових не створюємо —
        // вони потраплять в базу тільки після /start в боті.
        $member = Member::where('telegram_id', $telegramId)->first();
        if (!$member) {
            Log::info('[ChannelTracking] Пропускаємо — member не стартував бота', [
                'telegram_id' => $telegramId,
            ]);
            return;
        }

        if ($this->isJoinedStatus($newStatus) && $this->isLeftStatus($oldStatus)) {
            $inviteUrl = $raw['invite_link']['invite_link'] ?? null;
            $this->handleChannelJoin($member, $inviteUrl);
        } elseif ($this->isLeftStatus($newStatus) && $this->isJoinedStatus($oldStatus)) {
            $this->handleChannelLeave($member);
        }

        $member->save();
    }

    public function markChannelLinkClicked(Member $member): void
    {
        $member->channel_link_clicked_at = now();
        $member->save();
    }

    public function syncSubscriptionStatus(Member $member, string $telegramChatId, ?Api $telegram = null): bool
    {
        $telegram ??= new Api(config('telegram.bots.mybot.token'));
        $channelId = $this->getChannelChatId();
        if (!$channelId) {
            return false;
        }

        try {
            $chatMember = $telegram->getChatMember([
                'chat_id' => $channelId,
                'user_id' => $telegramChatId,
            ]);

            $status = $this->extractChatMemberStatus($chatMember);

            $isSubscribed = in_array($status, ['member', 'administrator', 'creator', 'restricted'], true);
            $wasSubscribed = $member->is_subscribed; // зберігаємо оригінальне значення (може бути null)

            $member->is_subscribed = $isSubscribed;

            if ($isSubscribed && !$wasSubscribed) {
                $this->attributeJoinIfEligible($member);
                if (!$member->channel_joined_at) {
                    $member->channel_joined_at = now();
                }
                $this->recordSubscriptionEvent(
                    $member,
                    ChannelSubscriptionEvent::TYPE_JOIN,
                    [
                        'origin' => 'sync',
                        'status' => $status,
                    ]
                );
                Log::info('[ChannelTracking] syncSubscriptionStatus: юзер підписаний', [
                    'telegram_id' => $member->telegram_id,
                    'status' => $status,
                    'source' => $member->channel_join_source,
                ]);
            } elseif (!$isSubscribed && $wasSubscribed) {
                $member->is_subscribed = false;
                $this->recordSubscriptionEvent(
                    $member,
                    ChannelSubscriptionEvent::TYPE_LEAVE,
                    [
                        'origin' => 'sync',
                        'status' => $status,
                    ]
                );
                Log::info('[ChannelTracking] syncSubscriptionStatus: юзер відписаний (via getChatMember)', [
                    'telegram_id' => $member->telegram_id,
                    'status' => $status,
                ]);
            }

            $member->save();

            return $isSubscribed;
        } catch (\Throwable $e) {
            Log::warning('[ChannelTracking] getChatMember failed — статус не оновлено: ' . $e->getMessage(), [
                'telegram_id' => $member->telegram_id,
                'channel_id' => $this->getChannelChatId(),
            ]);

            return (bool) $member->is_subscribed;
        }
    }

    public function getChannelChatId(): ?string
    {
        $chatId = trim((string) ($this->settings->telegram_channel_chat_id ?? ''));
        if ($chatId !== '') {
            return $chatId;
        }

        $username = trim((string) ($this->settings->telegram_channel_username ?? ''));
        if ($username === '') {
            return null;
        }

        return str_starts_with($username, '@') ? $username : '@' . $username;
    }

    public function getBotInviteLink(): ?string
    {
        return filled($this->settings->bot_channel_invite_link)
            ? $this->settings->bot_channel_invite_link
            : null;
    }

    protected function handleChannelJoin(Member $member, ?string $inviteUrl = null): void
    {
        $member->is_subscribed = true;
        $member->channel_joined_at = now();

        $botLink = $this->getBotInviteLink();
        if ($inviteUrl && $botLink && $inviteUrl === $botLink) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_BOT;
        } elseif ($this->recentlyClickedBotLink($member)) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_BOT;
        } elseif (!$member->channel_join_source) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_ORGANIC;
        }

        $this->recordSubscriptionEvent(
            $member,
            ChannelSubscriptionEvent::TYPE_JOIN,
            [
                'origin' => 'chat_member',
                'invite_url' => $inviteUrl,
            ]
        );

        Log::info('[ChannelTracking] Користувач підписався на канал', [
            'telegram_id' => $member->telegram_id,
            'source' => $member->channel_join_source,
            'invite_url' => $inviteUrl,
            'bot_link' => $botLink,
            'invite_match' => $inviteUrl && $botLink && $inviteUrl === $botLink,
        ]);
    }

    protected function handleChannelLeave(Member $member): void
    {
        $member->is_subscribed = false;
        $this->recordSubscriptionEvent(
            $member,
            ChannelSubscriptionEvent::TYPE_LEAVE,
            [
                'origin' => 'chat_member',
            ]
        );
        Log::info('[ChannelTracking] Користувач відписався від каналу', [
            'telegram_id' => $member->telegram_id,
        ]);
    }

    protected function attributeJoinIfEligible(Member $member): void
    {
        if ($member->channel_join_source === Member::CHANNEL_JOIN_SOURCE_BOT) {
            return;
        }

        if ($this->recentlyClickedBotLink($member)) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_BOT;
            if (!$member->channel_joined_at) {
                $member->channel_joined_at = now();
            }
        } elseif (!$member->channel_join_source) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_UNKNOWN;
        }
    }

    protected function recentlyClickedBotLink(Member $member): bool
    {
        if (!$member->channel_link_clicked_at) {
            return false;
        }

        return $member->channel_link_clicked_at->greaterThan(now()->subHours(48));
    }

    protected function isTargetChannelRaw(string $chatId, ?string $chatUsername, string $channelId): bool
    {
        // Порівняння за username
        if ($chatUsername) {
            $normalizedChannel = ltrim(strtolower($channelId), '@');
            $normalizedChat    = strtolower($chatUsername);
            if ($normalizedChat === $normalizedChannel) {
                return true;
            }
        }

        // Порівняння за числовим ID
        $normalizedId = ltrim($channelId, '@');
        return $chatId === $normalizedId || $chatId === $channelId;
    }

    /** @deprecated Використовуй isTargetChannelRaw */
    protected function isTargetChannel($chat, string $channelId): bool
    {
        $chatUsername = $chat->getUsername();
        $normalizedChannel = ltrim(strtolower($channelId), '@');
        $normalizedChat = strtolower((string) $chatUsername);

        if ($normalizedChat && $normalizedChat === $normalizedChannel) {
            return true;
        }

        $chatId = (string) $chat->getId();
        $normalizedId = ltrim($channelId, '@');

        return $chatId === $normalizedId || $chatId === $channelId;
    }

    protected function isJoinedStatus(?string $status): bool
    {
        return in_array($status, ['member', 'administrator', 'creator', 'restricted'], true);
    }

    protected function isLeftStatus(?string $status): bool
    {
        return in_array($status, ['left', 'kicked'], true);
    }

    private function extractChatMemberStatus(mixed $chatMember): string
    {
        if (is_array($chatMember)) {
            return (string) ($chatMember['status'] ?? 'left');
        }

        if (!is_object($chatMember)) {
            return 'left';
        }

        // Спочатку пробуємо toArray() — найнадійніший спосіб з цим SDK
        if (method_exists($chatMember, 'toArray')) {
            $data = $chatMember->toArray();
            if (is_array($data) && isset($data['status'])) {
                return (string) $data['status'];
            }
        }

        // Fallback: прямий доступ до властивості
        $status = $chatMember->status ?? null;
        if ($status !== null && $status !== false) {
            return (string) $status;
        }

        return 'left';
    }

    private function recordSubscriptionEvent(Member $member, string $eventType, array $meta = []): void
    {
        if (!$member->telegram_id) {
            return;
        }

        $recentDuplicate = ChannelSubscriptionEvent::query()
            ->where('telegram_id', (string) $member->telegram_id)
            ->where('event_type', $eventType)
            ->where('occurred_at', '>=', now()->subMinutes(self::EVENT_DEDUP_MINUTES))
            ->exists();

        if ($recentDuplicate) {
            return;
        }

        ChannelSubscriptionEvent::query()->create([
            'member_id' => $member->id,
            'telegram_id' => (string) $member->telegram_id,
            'event_type' => $eventType,
            'source' => $member->channel_join_source,
            'occurred_at' => now(),
            'meta' => $meta,
        ]);
    }
}
