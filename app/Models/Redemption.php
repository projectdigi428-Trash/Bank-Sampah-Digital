<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Redemption extends Model
{
    protected $fillable = [
        'user_id',
        'reward_id',
        'quantity',
        'points_per_item',
        'total_points',
        'status',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'points_per_item' => 'integer',
            'total_points' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(WasteTransaction::class);
    }
}
