<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'code',
        'category',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'accumulated_depreciation',
        'status',
        'note',
        'last_depreciated_at',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'useful_life_months' => 'integer',
            'accumulated_depreciation' => 'decimal:2',
            'last_depreciated_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function monthlyDepreciation(): float
    {
        $depreciable = (float) $this->acquisition_cost - (float) $this->salvage_value;

        if ($this->useful_life_months <= 0 || $depreciable <= 0) {
            return 0.0;
        }

        return round($depreciable / $this->useful_life_months, 2);
    }

    public function bookValue(): float
    {
        return round((float) $this->acquisition_cost - (float) $this->accumulated_depreciation, 2);
    }
}
