<?php

declare(strict_types=1);

namespace App\Application\Report\UseCases;

use App\Application\Ledger\UseCases\BalanceSheet;
use App\Application\Ledger\UseCases\CashFlow;
use App\Application\Ledger\UseCases\ProfitLoss;
use App\Application\Ledger\UseCases\TrialBalance;
use App\Application\Report\DTOs\ReportExportDocument;
use App\Models\Company;
use Illuminate\Support\Carbon;

final class BuildReportExport
{
    public function __construct(
        private ProfitLoss $profitLoss,
        private CashFlow $cashFlow,
        private BalanceSheet $balanceSheet,
        private TrialBalance $trialBalance,
        private DailyReport $daily,
        private ConsolidationReport $consolidation,
    ) {}

    public function __invoke(
        Company $company,
        string $report,
        string $from,
        string $to,
        ?int $branchId = null,
    ): ReportExportDocument {
        $branchName = $branchId === null
            ? null
            : $company->branches()->whereKey($branchId)->value('name');
        $period = $this->period($from, $to);

        return match ($report) {
            'profit-loss' => $this->profitLossDocument($company, $from, $to, $branchId, $branchName, $period),
            'daily' => $this->dailyDocument($company, $from, $to, $branchId, $branchName, $period),
            'cash-flow' => $this->cashFlowDocument($company, $from, $to, $branchId, $branchName, $period),
            'balance-sheet' => $this->balanceSheetDocument($company, $to),
            'trial-balance' => $this->trialBalanceDocument($company, $from, $to, $period),
            'consolidation' => $this->consolidationDocument($company, $from, $to, $period),
            default => abort(404),
        };
    }

    private function profitLossDocument(
        Company $company,
        string $from,
        string $to,
        ?int $branchId,
        ?string $branchName,
        string $period,
    ): ReportExportDocument {
        $result = ($this->profitLoss)($company, $from, $to, $branchId);

        return new ReportExportDocument(
            title: 'Laporan laba rugi',
            periodLabel: "Periode {$period}",
            filename: "laba-rugi-{$from}_{$to}",
            branchName: $branchName,
            summary: [
                $this->money('Pendapatan', $result['pendapatan']),
                $this->money('HPP', $result['hpp']),
                $this->money('Laba kotor', $result['laba_kotor']),
                $this->money('Beban', $result['beban']),
                $this->money('Laba bersih', $result['laba_bersih']),
            ],
            sections: [
                [
                    'title' => 'Rincian akun',
                    'headings' => ['Kode', 'Nama akun', 'Saldo'],
                    'types' => ['text', 'text', 'money'],
                    'rows' => array_map(
                        fn (array $row): array => [
                            (string) $row['code'],
                            (string) $row['name'],
                            (float) $row['saldo'],
                        ],
                        $result['detail'],
                    ),
                ],
            ],
        );
    }

    private function dailyDocument(
        Company $company,
        string $from,
        string $to,
        ?int $branchId,
        ?string $branchName,
        string $period,
    ): ReportExportDocument {
        $result = ($this->daily)($company, $from, $to, $branchId);
        $names = $company->branches()->pluck('name', 'id');

        $rows = array_map(
            fn ($row): array => [
                Carbon::parse($row->date)->format('d/m/Y'),
                (string) $names->get($row->branchId, "Cabang #{$row->branchId}"),
                (float) $row->omzet,
                $row->transactions,
                (float) $row->hppTotal,
                (float) $row->discountTotal,
                (float) $row->taxTotal,
                (float) $row->cashTunai,
                (float) $row->refundTotal,
                (float) $row->netOmzet,
            ],
            $result['rows'],
        );
        $totals = $result['totals'];
        $rows[] = [
            'Total',
            '',
            (float) $totals['omzet'],
            (int) $totals['transactions'],
            (float) $totals['hpp'],
            (float) $totals['discount'],
            (float) $totals['tax'],
            (float) $totals['cash'],
            (float) $totals['refunds'],
            (float) $totals['net'],
        ];

        return new ReportExportDocument(
            title: 'Ringkasan harian',
            periodLabel: "Periode {$period}",
            filename: "laporan-harian-{$from}_{$to}",
            branchName: $branchName,
            summary: [
                $this->money('Omzet', $totals['omzet']),
                $this->number('Transaksi', $totals['transactions']),
                $this->money('Refund', $totals['refunds']),
                $this->money('Net omzet', $totals['net']),
            ],
            sections: [
                [
                    'title' => 'Ringkasan per cabang',
                    'headings' => ['Tanggal', 'Cabang', 'Omzet', 'Trx', 'HPP', 'Diskon', 'Pajak', 'Tunai', 'Refund', 'Net'],
                    'types' => ['text', 'text', 'money', 'number', 'money', 'money', 'money', 'money', 'money', 'money'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    private function cashFlowDocument(
        Company $company,
        string $from,
        string $to,
        ?int $branchId,
        ?string $branchName,
        string $period,
    ): ReportExportDocument {
        $result = ($this->cashFlow)($company, $from, $to, $branchId);

        return new ReportExportDocument(
            title: 'Laporan arus kas',
            periodLabel: "Periode {$period}",
            filename: "arus-kas-{$from}_{$to}",
            branchName: $branchName,
            summary: [
                $this->money('Kas masuk', $result['kas_masuk']),
                $this->money('Kas keluar', $result['kas_keluar']),
                $this->money('Kas bersih', $result['kas_bersih']),
            ],
            sections: [
                [
                    'title' => 'Rincian arus kas',
                    'headings' => ['Kode', 'Nama akun', 'Debit', 'Kredit', 'Bersih'],
                    'types' => ['text', 'text', 'money', 'money', 'money'],
                    'rows' => array_map(
                        fn (array $row): array => [
                            (string) $row['code'],
                            (string) $row['name'],
                            (float) $row['debit'],
                            (float) $row['credit'],
                            (float) $row['bersih'],
                        ],
                        $result['detail'],
                    ),
                ],
            ],
        );
    }

    private function trialBalanceDocument(
        Company $company,
        string $from,
        string $to,
        string $period,
    ): ReportExportDocument {
        $result = ($this->trialBalance)($company, $from, $to);

        return new ReportExportDocument(
            title: 'Neraca saldo',
            periodLabel: "Periode {$period}",
            filename: "neraca-saldo-{$from}_{$to}",
            branchName: null,
            summary: [
                $this->money('Total debit', $result['total_debit']),
                $this->money('Total kredit', $result['total_credit']),
            ],
            sections: [
                [
                    'title' => 'Saldo per akun',
                    'headings' => ['Kode', 'Nama akun', 'Debit', 'Kredit'],
                    'types' => ['text', 'text', 'money', 'money'],
                    'rows' => array_map(
                        fn (array $row): array => [
                            (string) $row['code'],
                            (string) $row['name'],
                            (float) $row['debit'],
                            (float) $row['credit'],
                        ],
                        $result['rows'],
                    ),
                ],
            ],
        );
    }

    private function balanceSheetDocument(Company $company, string $to): ReportExportDocument
    {
        $result = ($this->balanceSheet)($company, $to);
        $date = Carbon::parse($to)->format('d/m/Y');
        $aktiva = $result['aktiva'];
        $kewajiban = $result['kewajiban'];

        return new ReportExportDocument(
            title: 'Neraca',
            periodLabel: "Per {$date}",
            filename: "neraca-{$to}",
            branchName: null,
            summary: [
                $this->money('Total aktiva', $aktiva['total']),
                $this->money('Total kewajiban', $kewajiban['total']),
                $this->money('Modal', $result['modal']),
            ],
            sections: [
                [
                    'title' => 'Aktiva',
                    'headings' => ['Akun', 'Nilai'],
                    'types' => ['text', 'money'],
                    'rows' => [
                        ['Kas & bank', (float) $aktiva['kas']],
                        ['Piutang usaha', (float) $aktiva['piutang']],
                        ['Persediaan', (float) $aktiva['persediaan']],
                        ['Total aktiva', (float) $aktiva['total']],
                    ],
                ],
                [
                    'title' => 'Kewajiban',
                    'headings' => ['Akun', 'Nilai'],
                    'types' => ['text', 'money'],
                    'rows' => [
                        ['Hutang supplier', (float) $kewajiban['hutang_supplier']],
                        ['Hutang pajak', (float) $kewajiban['hutang_pajak']],
                        ['Hutang gaji', (float) $kewajiban['hutang_gaji']],
                        ['Total kewajiban', (float) $kewajiban['total']],
                    ],
                ],
                [
                    'title' => 'Modal',
                    'headings' => ['Akun', 'Nilai'],
                    'types' => ['text', 'money'],
                    'rows' => [
                        ['Modal', (float) $result['modal']],
                    ],
                ],
            ],
        );
    }

    private function consolidationDocument(
        Company $company,
        string $from,
        string $to,
        string $period,
    ): ReportExportDocument {
        $result = ($this->consolidation)($company, $from, $to);

        $rows = array_map(
            fn (array $row): array => [
                "{$row['branchCode']} - {$row['branchName']}",
                (float) $row['omzet'],
                (int) $row['transactions'],
                (float) $row['hpp'],
                (float) $row['refunds'],
                (float) $row['net'],
            ],
            $result['branches'],
        );
        $grand = $result['grand'];
        $rows[] = [
            'Total grup',
            (float) $grand['omzet'],
            (int) $grand['transactions'],
            (float) $grand['hpp'],
            (float) $grand['refunds'],
            (float) $grand['net'],
        ];

        return new ReportExportDocument(
            title: 'Laporan konsolidasi',
            periodLabel: "Periode {$period}",
            filename: "konsolidasi-{$from}_{$to}",
            branchName: null,
            summary: [
                $this->money('Omzet grup', $grand['omzet']),
                $this->number('Transaksi', $grand['transactions']),
                $this->money('HPP', $grand['hpp']),
                $this->money('Net omzet', $grand['net']),
            ],
            sections: [
                [
                    'title' => 'Konsolidasi semua cabang',
                    'headings' => ['Cabang', 'Omzet', 'Trx', 'HPP', 'Refund', 'Net'],
                    'types' => ['text', 'money', 'number', 'money', 'money', 'money'],
                    'rows' => $rows,
                ],
            ],
        );
    }

    /** @return array{label: string, value: float, type: 'money'} */
    private function money(string $label, float|int $value): array
    {
        return ['label' => $label, 'value' => (float) $value, 'type' => 'money'];
    }

    /** @return array{label: string, value: int, type: 'number'} */
    private function number(string $label, int|float $value): array
    {
        return ['label' => $label, 'value' => (int) $value, 'type' => 'number'];
    }

    private function period(string $from, string $to): string
    {
        $start = Carbon::parse($from)->format('d/m/Y');
        $end = Carbon::parse($to)->format('d/m/Y');

        return $start === $end ? $end : "{$start} - {$end}";
    }
}
