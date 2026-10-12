<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory;

    public const MONTHLY = 'monthly_fixed';

    public const DAILY = 'daily_rate';

    public const COMMISSION = 'commission';

    public const MANUAL = 'manual';

    protected $fillable = [
        'company_id',
        'name',
        'calc_type',
        'deductible',
        'taxable',
        'bpjs_base',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'deductible' => 'boolean',
            'taxable' => 'boolean',
            'bpjs_base' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }
}
