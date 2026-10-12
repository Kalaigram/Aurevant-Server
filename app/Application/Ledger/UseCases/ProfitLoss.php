<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCases;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Models\Company;

final class ProfitLoss
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        $rows = $this->ledger->totalsByAccount($company, $from, $to, $branchId);

        $pendapatan = 0.0;
        $hpp = 0.0;
        $beban = 0.0;
        $detail = [];

        foreach ($rows as $row) {
            $saldo = $row['credit'] - $row['debit'];
            $code = $row['code'];

            if (str_starts_with($code, '4')) {
                $pendapatan += $saldo;
                $detail[] = $row + ['saldo' => round($saldo, 2)];
            } elseif (str_starts_with($code, '51')) {
                $hpp += -$saldo;
                $detail[] = $row + ['saldo' => round(-$saldo, 2)];
            } elseif (str_starts_with($code, '6')) {
                $beban += -$saldo;
                $detail[] = $row + ['saldo' => round(-$saldo, 2)];
            }
        }

        $labaKotor = $pendapatan - $hpp;

        return [
            'detail' => $detail,
            'pendapatan' => round($pendapatan, 2),
            'hpp' => round($hpp, 2),
            'laba_kotor' => round($labaKotor, 2),
            'beban' => round($beban, 2),
            'laba_bersih' => round($labaKotor - $beban, 2),
        ];
    }
}
