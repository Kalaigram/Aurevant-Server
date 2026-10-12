<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Finance\UseCases\ManageReceivables;
use App\Application\Ledger\UseCases\CashFlow;
use App\Application\Ledger\UseCases\ProfitLoss;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Attendance;
use App\Models\CashShift;
use App\Models\Company;
use App\Models\DailyBranchSummary;
use App\Models\EmployeeAssignment;
use App\Models\LeaveRequest;
use App\Models\PayrollPeriod;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Access;

/** Ringkasan per role: tiap seksi hanya muncul bila user punya izinnya. */
final class CompanyDashboard
{
    public function __construct(
        private CashFlow $cashFlow,
        private ProfitLoss $profitLoss,
        private ManageReceivables $receivables,
        private SubscriptionRepositoryInterface $billing,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, User $user): array
    {
        setPermissionsTeamId($company->id);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $branches = Access::scopeBranches($user, $company);

        $data = [
            'company' => ['id' => $company->id, 'name' => $company->name, 'status' => $company->status],
            'subscription' => $this->subscription($company),
        ];

        if ($user->hasPermissionTo('keuangan.lihat')) {
            $data['keuangan'] = $this->finance($company);
        }

        if ($user->hasPermissionTo('nota.lihat')) {
            $data['penjualan'] = $this->sales($company, $today, $branches);
        }

        if ($user->hasPermissionTo('stok.transfer') || $user->hasPermissionTo('stok.opname')) {
            $data['operasional'] = $this->operations($company, $user);
        }

        if ($user->hasPermissionTo('jadwal.lihat')) {
            $data['sdm'] = $this->hr($company, $today, $branches);
        }

        if ($user->hasPermissionTo('gaji.kelola')) {
            $data['penggajian'] = $this->payroll($company);
        }

        if ($user->hasPermissionTo('karyawan.kelola')) {
            $data['karyawan'] = [
                'total' => EmployeeAssignment::query()
                    ->where('company_id', $company->id)
                    ->whereNull('ended_at')
                    ->distinct('user_id')
                    ->count('user_id'),
            ];
        }

        return $data + ['month_start' => $monthStart, 'today' => $today];
    }

    /** @return array<string, mixed> */
    private function subscription(Company $company): array
    {
        $subscription = $this->billing->findForCompany($company);

        return $subscription === null
            ? ['status' => 'tanpa_langganan', 'ends_at' => null]
            : [
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toDateString(),
            ];
    }

    /** @return array<string, mixed> */
    private function finance(Company $company): array
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();
        $flow = ($this->cashFlow)($company, $from, $to);
        $profit = ($this->profitLoss)($company, $from, $to);
        $hutang = $this->receivables->supplierAging($company);
        $piutang = $this->receivables->aging($company);

        return [
            'kas_bersih_bulan_ini' => $flow['kas_bersih'],
            'laba_bersih_bulan_ini' => $profit['laba_bersih'],
            'hutang_total' => $hutang['total'],
            'piutang_total' => $piutang['total'],
        ];
    }

    /** @return array<string, mixed> */
    private function sales(Company $company, string $today, ?array $branches): array
    {
        $summary = DailyBranchSummary::query()
            ->where('company_id', $company->id)
            ->whereDate('date', $today)
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->selectRaw('COALESCE(SUM(net_omzet),0) AS omzet, COALESCE(SUM(transactions),0) AS transaksi')
            ->first();

        $openShifts = CashShift::query()
            ->where('company_id', $company->id)
            ->where('status', 'terbuka')
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->count();

        return [
            'omzet_hari_ini' => round((float) ($summary->omzet ?? 0), 2),
            'transaksi_hari_ini' => (int) ($summary->transaksi ?? 0),
            'shift_terbuka' => $openShifts,
        ];
    }

    /** @return array<string, mixed> */
    private function operations(Company $company, User $user): array
    {
        $data = [];

        if ($user->hasPermissionTo('stok.transfer')) {
            $data['transfer_diajukan'] = StockTransfer::query()
                ->where('company_id', $company->id)
                ->where('status', 'diajukan')
                ->count();
        }

        if ($user->hasPermissionTo('stok.opname')) {
            $data['opname_diajukan'] = StockOpname::query()
                ->where('company_id', $company->id)
                ->where('status', 'diajukan')
                ->count();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function hr(Company $company, string $today, ?array $branches): array
    {
        $counts = Attendance::query()
            ->where('company_id', $company->id)
            ->whereDate('date', $today)
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'hadir' => (int) ($counts['hadir'] ?? 0),
            'terlambat' => (int) ($counts['terlambat'] ?? 0),
            'alpa' => (int) ($counts['alpa'] ?? 0),
            'cuti_diajukan' => LeaveRequest::query()
                ->where('company_id', $company->id)
                ->where('status', 'diajukan')
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function payroll(Company $company): array
    {
        return PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
