<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Payroll\DTOs\PayrollLineDTO;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ManageAdjustments
{
    public function __construct(
        private PayrollRepositoryInterface $payrolls,
    ) {}

    public function add(PayrollPeriod $period, User $user, string $label, float $amount): PayrollLineDTO
    {
        if (! in_array($period->status, ['draft', 'review'], true)) {
            throw ValidationException::withMessages([
                'period' => 'Periode terkunci.',
            ]);
        }

        return PayrollLineDTO::fromModel(
            $this->payrolls->addManualLine($period, $user, $label, $amount)
        );
    }
}
