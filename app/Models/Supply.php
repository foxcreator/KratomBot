<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    protected $fillable = [
        'number',
        'date',
        'supplier_name',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function supplyItems()
    {
        return $this->hasMany(SupplyItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
