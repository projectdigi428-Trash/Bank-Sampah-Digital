<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WasteType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price_per_kg',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kg' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function collectionPoints(): BelongsToMany
    {
        return $this->belongsToMany(CollectionPoint::class);
    }
}
