<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Payroll\DTOs\PayrollPeriodDTO;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Models\PayrollPeriod;
use Illuminate\Validation\ValidationException;

final class TransitionPayroll
{
    public function __construct(
        private PayrollRepositoryInterface $payrolls,
        private LedgerPoster $ledgerPoster,
    ) {}

    public function review(PayrollPeriod $period): PayrollPeriodDTO
    {
        return $this->move($period, 'draft', 'review', 'payroll.review');
    }

    public function approve(PayrollPeriod $period): PayrollPeriodDTO
    {
        $dto = $this->move($period, 'review', 'disetujui', 'payroll.approve');
        ($this->ledgerPoster)->forPayrollAccrue($period, $this->payrolls->grossForPeriod($period));

        return $dto;
    }

    public function pay(PayrollPeriod $period): PayrollPeriodDTO
    {
        $dto = $this->move($period, 'disetujui', 'dibayar', 'payroll.pay');
        ($this->ledgerPoster)->forPayrollPay($period, $this->payrolls->grossForPeriod($period));

        return $dto;
    }

    private function move(PayrollPeriod $period, string $from, string $to, string $action): PayrollPeriodDTO
    {
        if ($period->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'Periode harus berstatus '.$from.' (saat ini: '.$period->status.').',
            ]);
        }

        $period->status = $to;
        $saved = $this->payrolls->savePeriod($period);

        Audit::record(
            $period->company, null, $action, 'payroll_period', $period->id,
            ['status' => $from],
            ['status' => $to],
        );

        return PayrollPeriodDTO::fromModel($saved);
    }
}
