<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deposit extends Model
{
    protected $fillable = [
        'user_id',
        'officer_id',
        'waste_type_id',
        'weight_kg',
        'price_per_kg',
        'total_points',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
            'price_per_kg' => 'integer',
            'total_points' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(WasteTransaction::class);
    }
}
