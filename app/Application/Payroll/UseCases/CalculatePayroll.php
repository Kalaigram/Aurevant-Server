<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Payroll\DTOs\PayrollPeriodDTO;
use App\Domain\Attendance\Services\AttendanceStatuses;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Domain\Payroll\Repositories\SalaryRepositoryInterface;
use App\Domain\Payroll\Services\SalaryCalculator;
use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CalculatePayroll
{
    public function __construct(
        private PayrollRepositoryInterface $payrolls,
        private SalaryRepositoryInterface $salaries,
        private SalaryCalculator $calculator,
    ) {}

    /** @return array{period: PayrollPeriodDTO, employees: list<array{user_id: int, net: float}>} */
    public function __invoke(Company $company, PayrollPeriod $period): array
    {
        if (! in_array($period->status, ['draft', 'review'], true)) {
            throw ValidationException::withMessages([
                'period' => 'Periode terkunci.',
            ]);
        }

        return DB::transaction(function () use ($company, $period) {
            $userIds = $company->employeeSalaries()->distinct()->pluck('user_id')->all();
            $summary = [];

            foreach ($userIds as $userId) {
                $user = User::query()->whereKey($userId)->first();
                if ($user === null) {
                    continue;
                }

                $attendances = $user->attendances()
                    ->whereBetween('date', [$period->date_from->toDateString(), $period->date_to->toDateString()])
                    ->pluck('status')
                    ->all();

                $present = 0;
                $absent = 0;
                foreach ($attendances as $status) {
                    if (in_array($status, ['hadir', 'terlambat'], true)) {
                        $present++;
                    } elseif ((AttendanceStatuses::all()[$status] ?? false) === true) {
                        $absent++;
                    }
                }

                $commission = ServiceOrderItem::query()
                    ->join('service_orders', 'service_orders.id', '=', 'service_order_items.service_order_id')
                    ->where('service_orders.company_id', $company->id)
                    ->where('service_orders.assignee_id', $user->id)
                    ->whereIn('service_orders.status', ['selesai', 'dibayar'])
                    ->whereBetween('service_orders.updated_at', [
                        $period->date_from->startOfDay(), $period->date_to->endOfDay(),
                    ])
                    ->sum('service_order_items.commission_amount');

                $lines = $this->calculator->calculate(
                    $this->salaries->componentsForUser($company, $user),
                    $present,
                    $absent,
                    $period->divisor,
                    (float) $commission,
                    $user->ptkp_status ?? 'TK/0',
                );

                $this->payrolls->replaceAutoLines($period, $user, $lines);
                $summary[] = [
                    'user_id' => $user->id,
                    'net' => round(array_sum(array_column($lines, 'amount')), 2),
                ];
            }

            return ['period' => PayrollPeriodDTO::fromModel($period), 'employees' => $summary];
        });
    }
}
