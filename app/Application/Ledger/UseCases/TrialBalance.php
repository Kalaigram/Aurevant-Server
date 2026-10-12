<?php

declare(strict_types=1);

namespace App\Application\Ledger\UseCases;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Models\Company;

final class TrialBalance
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, total_debit: float, total_credit: float} */
    public function __invoke(Company $company, string $from, string $to): array
    {
        $rows = $this->ledger->totalsByAccount($company, $from, $to);
        $debit = 0.0;
        $credit = 0.0;

        foreach ($rows as $row) {
            $debit += $row['debit'];
            $credit += $row['credit'];
        }

        return [
            'rows' => $rows,
            'total_debit' => round($debit, 2),
            'total_credit' => round($credit, 2),
        ];
    }
}
