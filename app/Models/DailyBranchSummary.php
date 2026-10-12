<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBranchSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'date',
        'omzet',
        'transactions',
        'hpp_total',
        'discount_total',
        'tax_total',
        'cash_tunai',
        'refund_total',
        'net_omzet',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'omzet' => 'decimal:2',
            'hpp_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'cash_tunai' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'net_omzet' => 'decimal:2',
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
}
