<?php

namespace App\Services;

use App\Models\Member;
use App\Settings\TelegramSettings;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramChannelTrackingService
{
    public const INVITE_LINK_NAME = 'kratom_bot_tracking';

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
        // ДІАГНОСТИКА — видалити після перевірки
        Log::error('[ChannelTracking][DEBUG] chat_member update received', [
            'raw' => method_exists($update, 'toArray') ? $update->toArray() : [],
        ]);

        $chatMemberUpdate = $update->getChatMember();
        if (!$chatMemberUpdate) {
            Log::error('[ChannelTracking][DEBUG] getChatMember() повернув null');
            return;
        }

        $chat = $chatMemberUpdate->getChat();
        $channelId = $this->getChannelChatId();

        Log::error('[ChannelTracking][DEBUG] channel check', [
            'settings_channel_id' => $channelId,
            'chat_id_from_update' => $chat ? $chat->getId() : null,
            'chat_username_from_update' => $chat ? $chat->getUsername() : null,
            'is_target' => $channelId && $chat ? $this->isTargetChannel($chat, $channelId) : false,
        ]);

        if (!$channelId || !$this->isTargetChannel($chat, $channelId)) {
            return;
        }

        $newMember = $chatMemberUpdate->getNewChatMember();
        $oldMember = $chatMemberUpdate->getOldChatMember();

        Log::error('[ChannelTracking][DEBUG] members', [
            'newMember_type' => gettype($newMember),
            'oldMember_type' => gettype($oldMember),
            'newMember_null' => $newMember === null,
            'oldMember_null' => $oldMember === null,
        ]);

        if (!$newMember || !$oldMember) {
            Log::error('[ChannelTracking][DEBUG] newMember або oldMember = null, виходимо');
            return;
        }

        $user = $newMember->getUser();
        $telegramId = (string) $user?->getId();
        $newStatus = $newMember->getStatus();
        $oldStatus = $oldMember->getStatus();

        Log::error('[ChannelTracking][DEBUG] statuses', [
            'telegram_id' => $telegramId,
            'newStatus' => $newStatus,
            'oldStatus' => $oldStatus,
            'isJoined' => $this->isJoinedStatus($newStatus),
            'wasLeft' => $this->isLeftStatus($oldStatus),
        ]);

        if (!$user) {
            Log::error('[ChannelTracking][DEBUG] user = null, виходимо');
            return;
        }

        $member = Member::query()->firstOrNew(['telegram_id' => $telegramId]);
        if (!$member->exists) {
            $member->username = $user->getUsername();
            $member->full_name = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? '')) ?: ('id' . $telegramId);
        }

        if ($this->isJoinedStatus($newStatus) && $this->isLeftStatus($oldStatus)) {
            Log::error('[ChannelTracking][DEBUG] → handleChannelJoin викликається');
            $this->handleChannelJoin($member, $chatMemberUpdate);
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
                Log::info('[ChannelTracking] syncSubscriptionStatus: юзер підписаний', [
                    'telegram_id' => $member->telegram_id,
                    'status' => $status,
                    'source' => $member->channel_join_source,
                ]);
            } elseif (!$isSubscribed && $wasSubscribed) {
                $member->is_subscribed = false;
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

    protected function handleChannelJoin(Member $member, $chatMemberUpdate): void
    {
        $member->is_subscribed = true;
        $member->channel_joined_at = now();

        $inviteLink = $chatMemberUpdate->getInviteLink();
        $inviteUrl = null;
        if ($inviteLink) {
            $inviteUrl = is_object($inviteLink)
                ? ($inviteLink->inviteLink ?? $inviteLink->invite_link ?? null)
                : ($inviteLink['invite_link'] ?? null);
        }

        $botLink = $this->getBotInviteLink();
        if ($inviteUrl && $botLink && $inviteUrl === $botLink) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_BOT;
        } elseif ($this->recentlyClickedBotLink($member)) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_BOT;
        } elseif (!$member->channel_join_source) {
            $member->channel_join_source = Member::CHANNEL_JOIN_SOURCE_ORGANIC;
        }

        Log::info('[ChannelTracking] Користувач підписався на канал', [
            'telegram_id' => $member->telegram_id,
            'source' => $member->channel_join_source,
            'invite_match' => $inviteUrl && $botLink && $inviteUrl === $botLink,
        ]);
    }

    protected function handleChannelLeave(Member $member): void
    {
        $member->is_subscribed = false;
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

        if (! is_object($chatMember)) {
            return 'left';
        }

        if (method_exists($chatMember, 'getStatus')) {
            return (string) ($chatMember->getStatus() ?? 'left');
        }

        if (method_exists($chatMember, 'toArray')) {
            $data = $chatMember->toArray();
            if (is_array($data)) {
                return (string) ($data['status'] ?? 'left');
            }
        }

        return (string) ($chatMember->status ?? 'left');
    }
}
