<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'year',
        'entitlement',
        'carryover',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitlement' => 'decimal:2',
            'carryover' => 'decimal:2',
            'used' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function available(): float
    {
        return round((float) $this->entitlement + (float) $this->carryover - (float) $this->used, 2);
    }
}
