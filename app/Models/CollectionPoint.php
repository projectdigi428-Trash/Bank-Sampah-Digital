<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CollectionPoint extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function wasteTypes(): BelongsToMany
    {
        return $this->belongsToMany(WasteType::class);
    }
}
