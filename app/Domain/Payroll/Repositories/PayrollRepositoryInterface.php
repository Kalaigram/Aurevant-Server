<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Models\Company;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface PayrollRepositoryInterface
{
    /** @return LengthAwarePaginator<int, PayrollPeriod> */
    public function paginatePeriods(Company $company, int $perPage): LengthAwarePaginator;

    public function findPeriod(Company $company, int $id): ?PayrollPeriod;

    public function createPeriod(Company $company, array $attributes): PayrollPeriod;

    public function savePeriod(PayrollPeriod $period): PayrollPeriod;

    public function replaceAutoLines(PayrollPeriod $period, User $user, array $lines): void;

    public function addManualLine(PayrollPeriod $period, User $user, string $label, float $amount): PayrollLine;

    /** @return list<PayrollLine> */
    public function linesForUser(PayrollPeriod $period, User $user): array;
}
