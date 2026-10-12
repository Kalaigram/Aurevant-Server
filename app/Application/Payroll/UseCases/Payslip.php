<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Payroll\DTOs\PayrollLineDTO;
use App\Application\Payroll\DTOs\PayrollPeriodDTO;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Models\Company;
use App\Models\User;

final class Payslip
{
    public function __construct(
        private PayrollRepositoryInterface $payrolls,
    ) {}

    /** @return array{period: PayrollPeriodDTO, lines: list<PayrollLineDTO>, net: float} */
    public function __invoke(Company $company, int $periodId, User $user): array
    {
        $period = $this->payrolls->findPeriod($company, $periodId) ?? abort(404);
        $lines = array_map(
            fn ($line) => PayrollLineDTO::fromModel($line),
            $this->payrolls->linesForUser($period, $user)
        );

        return [
            'period' => PayrollPeriodDTO::fromModel($period),
            'lines' => $lines,
            'net' => round(array_sum(array_map(fn ($line) => (float) $line->amount, $lines)), 2),
        ];
    }
}
