<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    public const ACTIVE = ['trial', 'aktif', 'tenggang'];

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function allowsWrites(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
