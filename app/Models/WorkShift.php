<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'starts_at',
        'ends_at',
        'late_tolerance_minutes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:H:i',
            'ends_at' => 'datetime:H:i',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isOvernight(): bool
    {
        return $this->ends_at->format('H:i') <= $this->starts_at->format('H:i');
    }
}
