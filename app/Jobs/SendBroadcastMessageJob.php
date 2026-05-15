<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Services\TelegramHtmlConverter;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $recipientId)
    {
    }

    public function handle(TelegramService $telegram): void
    {
        /** @var BroadcastRecipient|null $recipient */
        $recipient = BroadcastRecipient::with('broadcast')->find($this->recipientId);

        if (!$recipient) {
            return;
        }

        $broadcast = $recipient->broadcast;

        if (!$broadcast || $broadcast->status === Broadcast::STATUS_CANCELLED) {
            if ($recipient->status === BroadcastRecipient::STATUS_PENDING) {
                $recipient->update([
                    'status' => BroadcastRecipient::STATUS_SKIPPED,
                    'error_message' => 'Розсилку скасовано',
                ]);
                $broadcast?->refreshCounters();
            }
            return;
        }

        if ($recipient->status !== BroadcastRecipient::STATUS_PENDING) {
            return;
        }

        if ($broadcast->status === Broadcast::STATUS_PENDING) {
            $broadcast->update([
                'status' => Broadcast::STATUS_IN_PROGRESS,
                'started_at' => $broadcast->started_at ?? now(),
            ]);
        }

        if (empty($recipient->telegram_id)) {
            $recipient->update([
                'status' => BroadcastRecipient::STATUS_SKIPPED,
                'error_message' => 'Відсутній telegram_id',
            ]);
            $broadcast->refreshCounters();
            return;
        }

        try {
            $parseMode = $broadcast->getEffectiveParseMode();
            $rawMessage = (string) $broadcast->message;
            $message = $parseMode === Broadcast::PARSE_MODE_HTML
                ? app(TelegramHtmlConverter::class)->convert($rawMessage)
                : $rawMessage;

            $hasImage = !empty($broadcast->image_path)
                && Storage::disk('public')->exists($broadcast->image_path);

            if ($hasImage) {
                $absolutePath = Storage::disk('public')->path($broadcast->image_path);
                $messageLength = mb_strlen($message);

                if ($messageLength <= 1024) {
                    $telegram->sendPhoto(
                        $recipient->telegram_id,
                        $absolutePath,
                        $message !== '' ? $message : null,
                        $parseMode
                    );
                } else {
                    $telegram->sendPhoto($recipient->telegram_id, $absolutePath);
                    $telegram->sendMessage($recipient->telegram_id, $message, $parseMode);
                }
            } else {
                $telegram->sendMessage($recipient->telegram_id, $message, $parseMode);
            }

            $recipient->update([
                'status' => BroadcastRecipient::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Broadcast] failed to send message', [
                'broadcast_id' => $broadcast->id,
                'recipient_id' => $recipient->id,
                'telegram_id' => $recipient->telegram_id,
                'error' => $e->getMessage(),
            ]);

            $recipient->update([
                'status' => BroadcastRecipient::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }

        $broadcast->refreshCounters();
    }
}
