<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelSubscriptionEvent extends Model
{
    use HasFactory;

    public const TYPE_JOIN = 'join';
    public const TYPE_LEAVE = 'leave';

    protected $fillable = [
        'member_id',
        'telegram_id',
        'event_type',
        'source',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
