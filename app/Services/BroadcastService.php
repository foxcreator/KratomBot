<?php

namespace App\Services;

use App\Jobs\SendBroadcastMessageJob;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BroadcastService
{
    public const MIN_INTERVAL_SECONDS = 30;
    public const MAX_INTERVAL_SECONDS = 60;

    public function create(array $data, ?int $userId = null): Broadcast
    {
        return DB::transaction(function () use ($data, $userId) {
            $excludedIds = array_map('intval', $data['excluded_member_ids'] ?? []);

            $broadcast = Broadcast::create([
                'message' => $data['message'],
                'image_path' => $data['image_path'] ?? null,
                'parse_mode' => Broadcast::PARSE_MODE_HTML,
                'audience' => $data['audience'] ?? Broadcast::AUDIENCE_ALL,
                'status' => Broadcast::STATUS_PENDING,
                'created_by_user_id' => $userId,
            ]);

            $members = $this->resolveAudience($broadcast->audience)
                ->when(!empty($excludedIds), fn ($q) => $q->whereNotIn('id', $excludedIds))
                ->get(['id', 'telegram_id']);

            $now = now();
            $rows = [];
            foreach ($members as $member) {
                $rows[] = [
                    'broadcast_id' => $broadcast->id,
                    'member_id' => $member->id,
                    'telegram_id' => $member->telegram_id,
                    'status' => BroadcastRecipient::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    BroadcastRecipient::insert($chunk);
                }
            }

            $broadcast->total_recipients = count($rows);
            $broadcast->save();

            return $broadcast;
        });
    }

    public function dispatch(Broadcast $broadcast): void
    {
        $recipients = $broadcast->recipients()
            ->where('status', BroadcastRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->get(['id']);

        $delay = 0;
        foreach ($recipients as $recipient) {
            SendBroadcastMessageJob::dispatch($recipient->id)
                ->delay(now()->addSeconds($delay));

            $delay += random_int(self::MIN_INTERVAL_SECONDS, self::MAX_INTERVAL_SECONDS);
        }

        if ($broadcast->status === Broadcast::STATUS_PENDING && $recipients->isNotEmpty()) {
            $broadcast->update(['status' => Broadcast::STATUS_IN_PROGRESS]);
        }

        if ($recipients->isEmpty() && $broadcast->total_recipients === 0) {
            $broadcast->update([
                'status' => Broadcast::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);
        }
    }

    public function cancel(Broadcast $broadcast): void
    {
        if ($broadcast->isFinished()) {
            return;
        }

        DB::transaction(function () use ($broadcast) {
            $broadcast->recipients()
                ->where('status', BroadcastRecipient::STATUS_PENDING)
                ->update([
                    'status' => BroadcastRecipient::STATUS_SKIPPED,
                    'error_message' => 'Розсилку скасовано',
                    'updated_at' => now(),
                ]);

            $broadcast->update([
                'status' => Broadcast::STATUS_CANCELLED,
                'finished_at' => now(),
            ]);

            $broadcast->refreshCounters();
        });
    }

    public function audienceQuery(string $audience): Builder
    {
        return $this->resolveAudience($audience);
    }

    protected function resolveAudience(string $audience): Builder
    {
        $query = Member::query()
            ->whereNotNull('telegram_id')
            ->where('telegram_id', '!=', '');

        if ($audience === Broadcast::AUDIENCE_SUBSCRIBED) {
            $query->where('is_subscribed', true);
        } elseif ($audience === Broadcast::AUDIENCE_UNSUBSCRIBED) {
            $query->where(function (Builder $q) {
                $q->where('is_subscribed', false)->orWhereNull('is_subscribed');
            });
        }

        return $query;
    }
}
