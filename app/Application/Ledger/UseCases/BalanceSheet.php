<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCases;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Models\Company;

final class BalanceSheet
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, string $asOf): array
    {
        $rows = $this->ledger->totalsByAccount($company, '2000-01-01', $asOf);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row['code']] = $row;
        }

        $saldo = fn (string $code): float => ($byCode[$code]['debit'] ?? 0) - ($byCode[$code]['credit'] ?? 0);
        $saldoKredit = fn (string $code): float => ($byCode[$code]['credit'] ?? 0) - ($byCode[$code]['debit'] ?? 0);

        $aktiva = [
            'kas' => round($saldo('1110') + $saldo('1120'), 2),
            'piutang' => round($saldo('1210'), 2),
            'persediaan' => round($saldo('1310'), 2),
        ];
        $kewajiban = [
            'hutang_supplier' => round($saldoKredit('2110'), 2),
            'hutang_pajak' => round($saldoKredit('2130'), 2),
            'hutang_gaji' => round($saldoKredit('2140'), 2),
        ];

        $totalAktiva = round(array_sum($aktiva), 2);
        $totalKewajiban = round(array_sum($kewajiban), 2);

        return [
            'as_of' => $asOf,
            'aktiva' => $aktiva + ['total' => $totalAktiva],
            'kewajiban' => $kewajiban + ['total' => $totalKewajiban],
            'modal' => round($totalAktiva - $totalKewajiban, 2),
        ];
    }
}
