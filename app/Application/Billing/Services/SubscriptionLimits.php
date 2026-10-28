<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

final class SubscriptionLimits
{
    public function subscription(Company $company): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->first();
    }

    /** @return array{maxBranches: ?int, maxEmployees: ?int, branchesUsed: int, employeesUsed: int} */
    public function usage(Company $company): array
    {
        $plan = $this->subscription($company)?->plan;

        return [
            'maxBranches' => $plan?->max_branches !== null ? (int) $plan->max_branches : null,
            'maxEmployees' => $plan?->max_employees !== null ? (int) $plan->max_employees : null,
            'branchesUsed' => Branch::query()->where('company_id', $company->id)->count(),
            'employeesUsed' => EmployeeAssignment::query()
                ->where('company_id', $company->id)
                ->whereNull('ended_at')
                ->distinct()
                ->count('user_id'),
        ];
    }

    public function assertCanAddBranch(Company $company): void
    {
        $plan = $this->subscription($company)?->plan;

        if ($plan === null || $plan->max_branches === null) {
            return;
        }

        $used = Branch::query()->where('company_id', $company->id)->count();

        if ($used >= $plan->max_branches) {
            throw ValidationException::withMessages([
                'cabang' => 'Kuota cabang paket '.$plan->name.' sudah penuh ('.$used.'/'.$plan->max_branches.').',
            ]);
        }
    }

    public function assertCanAddEmployee(Company $company): void
    {
        $plan = $this->subscription($company)?->plan;

        if ($plan === null || $plan->max_employees === null) {
            return;
        }

        $used = EmployeeAssignment::query()
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->distinct()
            ->count('user_id');

        if ($used >= $plan->max_employees) {
            throw ValidationException::withMessages([
                'karyawan' => 'Kuota karyawan paket '.$plan->name.' sudah penuh ('.$used.'/'.$plan->max_employees.').',
            ]);
        }
    }

    public function planForCompany(Company $company): ?Plan
    {
        return $this->subscription($company)?->plan;
    }
}
