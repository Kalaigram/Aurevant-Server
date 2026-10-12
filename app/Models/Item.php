<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'code',
        'name',
        'type',
        'unit',
        'track_stock',
        'has_variants',
        'track_batch',
        'track_serial',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'track_stock' => 'boolean',
            'has_variants' => 'boolean',
            'track_batch' => 'boolean',
            'track_serial' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function consumables(): HasMany
    {
        return $this->hasMany(ItemConsumable::class);
    }
}
