<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WasteTransaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'deposit_id',
        'redemption_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }
}
