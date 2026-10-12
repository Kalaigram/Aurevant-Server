<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Models\Company;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class PayrollEloquentRepository implements PayrollRepositoryInterface
{
    public function paginatePeriods(Company $company, int $perPage): LengthAwarePaginator
    {
        return PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->orderByDesc('date_to')
            ->paginate($perPage);
    }

    public function findPeriod(Company $company, int $id): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createPeriod(Company $company, array $attributes): PayrollPeriod
    {
        return PayrollPeriod::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function savePeriod(PayrollPeriod $period): PayrollPeriod
    {
        $period->save();

        return $period->refresh();
    }

    public function replaceAutoLines(PayrollPeriod $period, User $user, array $lines): void
    {
        PayrollLine::query()
            ->where('payroll_period_id', $period->id)
            ->where('user_id', $user->id)
            ->where('is_manual', false)
            ->delete();

        foreach ($lines as $line) {
            PayrollLine::query()->create([
                'payroll_period_id' => $period->id,
                'user_id' => $user->id,
                'salary_component_id' => $line['component_id'],
                'label' => $line['label'],
                'amount' => $line['amount'],
                'is_manual' => false,
            ]);
        }
    }

    public function addManualLine(PayrollPeriod $period, User $user, string $label, float $amount): PayrollLine
    {
        return PayrollLine::query()->create([
            'payroll_period_id' => $period->id,
            'user_id' => $user->id,
            'salary_component_id' => null,
            'label' => $label,
            'amount' => $amount,
            'is_manual' => true,
        ])->refresh();
    }

    public function linesForUser(PayrollPeriod $period, User $user): array
    {
        return PayrollLine::query()
            ->where('payroll_period_id', $period->id)
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function grossForPeriod(PayrollPeriod $period): float
    {
        return (float) PayrollLine::query()
            ->where('payroll_period_id', $period->id)
            ->where('amount', '>', 0)
            ->sum('amount');
    }
}
