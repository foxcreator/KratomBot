<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use HasFactory;

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_SUBSCRIBED = 'subscribed';
    public const AUDIENCE_UNSUBSCRIBED = 'unsubscribed';
    public const AUDIENCE_SPECIFIC = 'specific';

    public const AUDIENCES = [
        self::AUDIENCE_ALL => 'Всі користувачі',
        self::AUDIENCE_SUBSCRIBED => 'З підпискою на канал',
        self::AUDIENCE_UNSUBSCRIBED => 'Без підписки на канал',
        self::AUDIENCE_SPECIFIC => 'Конкретні користувачі',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'В очікуванні',
        self::STATUS_IN_PROGRESS => 'Відправляється',
        self::STATUS_COMPLETED => 'Завершено',
        self::STATUS_CANCELLED => 'Скасовано',
    ];

    public const PARSE_MODE_NONE = 'none';
    public const PARSE_MODE_HTML = 'HTML';
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    public const PARSE_MODES = [
        self::PARSE_MODE_NONE => 'Без форматування',
        self::PARSE_MODE_HTML => 'HTML',
        self::PARSE_MODE_MARKDOWN => 'Markdown',
    ];

    protected $fillable = [
        'message',
        'image_path',
        'parse_mode',
        'audience',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by_user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_recipients <= 0) {
            return 0;
        }

        $processed = $this->sent_count + $this->failed_count;

        return (int) floor(($processed / $this->total_recipients) * 100);
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        return asset('storage/' . $this->image_path);
    }

    public function getEffectiveParseMode(): ?string
    {
        if (!$this->parse_mode || $this->parse_mode === self::PARSE_MODE_NONE) {
            return null;
        }

        return $this->parse_mode;
    }

    public function refreshCounters(): void
    {
        $this->sent_count = $this->recipients()->where('status', BroadcastRecipient::STATUS_SENT)->count();
        $this->failed_count = $this->recipients()->where('status', BroadcastRecipient::STATUS_FAILED)->count();

        $pending = $this->recipients()->where('status', BroadcastRecipient::STATUS_PENDING)->count();

        if ($pending === 0 && $this->status !== self::STATUS_CANCELLED) {
            $this->status = self::STATUS_COMPLETED;
            $this->finished_at = $this->finished_at ?? now();
        }

        $this->save();
    }
}
