<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocode extends Model
{
    use HasFactory;

    protected $fillable = ['member_id', 'code', 'is_used', 'store_name'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
