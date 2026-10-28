<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Application\Payroll\DTOs\PayrollPeriodDTO;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\User;
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

        $userIds = PayrollLine::query()
            ->where('payroll_period_id', $period->id)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            Notifier::toUser(
                $period->company,
                $user,
                'payroll.dibayar',
                'Gaji '.$period->name.' telah dibayar',
                'Slip gaji sudah tersedia. Buka menu Penggajian untuk melihat rinciannya.',
                '/payroll',
            );
        }

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
