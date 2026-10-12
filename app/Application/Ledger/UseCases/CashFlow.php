<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCases;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Models\Company;

final class CashFlow
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        $rows = $this->ledger->totalsByAccount($company, $from, $to, $branchId);

        $masuk = 0.0;
        $keluar = 0.0;
        $detail = [];

        foreach ($rows as $row) {
            if (! str_starts_with($row['code'], '11')) {
                continue;
            }

            $masuk += $row['debit'];
            $keluar += $row['credit'];
            $detail[] = $row + ['bersih' => round($row['debit'] - $row['credit'], 2)];
        }

        return [
            'detail' => $detail,
            'kas_masuk' => round($masuk, 2),
            'kas_keluar' => round($keluar, 2),
            'kas_bersih' => round($masuk - $keluar, 2),
        ];
    }
}
