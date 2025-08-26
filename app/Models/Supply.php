<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    protected $fillable = [
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Supply $supply) {
            if (empty($supply->number)) {
                // Генеруємо унікальний номер: SUP-YYYYMMDD-XXXX
                // Перебираємо поки не знайдемо вільний номер (на випадок гонки)
                do {
                    $candidate = 'SUP-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                } while (self::where('number', $candidate)->exists());

                $supply->number = $candidate;
            }
        });
    }
}
