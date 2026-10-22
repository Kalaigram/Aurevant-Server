<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\DTOs\AccountingPeriodDTO;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClosePeriod
{
    /** @return LengthAwarePaginator<int, AccountingPeriodDTO> */
    public function list(Company $company, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        return AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($perPage)
            ->through(fn ($period) => AccountingPeriodDTO::fromModel($period));
    }

    public function close(Company $company, int $year, int $month): AccountingPeriodDTO
    {
        $now = now();
        if ($year * 100 + $month > (int) $now->format('Y') * 100 + (int) $now->format('n')) {
            throw ValidationException::withMessages([
                'month' => 'Bulan berjalan/besok belum bisa ditutup.',
            ]);
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $draftExpenses = Expense::query()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->whereBetween('date', [$from, $to])
            ->count();

        if ($draftExpenses > 0) {
            throw ValidationException::withMessages([
                'periode' => "Masih ada {$draftExpenses} biaya draft di bulan ini.",
            ]);
        }

        $openPayrolls = PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', 'dibayar')
            ->where('date_to', '>=', $from)
            ->where('date_from', '<=', $to)
            ->count();

        if ($openPayrolls > 0) {
            throw ValidationException::withMessages([
                'periode' => 'Masih ada periode gaji belum dibayar di bulan ini.',
            ]);
        }

        return DB::transaction(function () use ($company, $year, $month) {
            $period = AccountingPeriod::query()->firstOrCreate(
                ['company_id' => $company->id, 'year' => $year, 'month' => $month],
                ['status' => 'buka'],
            );

            if ($period->isClosed()) {
                throw ValidationException::withMessages([
                    'periode' => 'Periode '.$period->label().' sudah ditutup.',
                ]);
            }

            $period->status = 'tutup';
            $period->closed_at = now();
            $period->save();

            Audit::record($company, null, 'periode.tutup', 'accounting_period', $period->id);

            return AccountingPeriodDTO::fromModel($period->refresh());
        });
    }

    public function reopen(Company $company, AccountingPeriod $period): AccountingPeriodDTO
    {
        return DB::transaction(function () use ($company, $period) {
            $period->status = 'buka';
            $period->closed_at = null;
            $period->save();

            Audit::record($company, null, 'periode.buka', 'accounting_period', $period->id);

            return AccountingPeriodDTO::fromModel($period->refresh());
        });
    }
}
