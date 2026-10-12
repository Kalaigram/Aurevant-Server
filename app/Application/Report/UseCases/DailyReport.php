<?php

declare(strict_types=1);

namespace App\Application\Report\UseCases;

use App\Application\Report\DTOs\DailySummaryDTO;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Report\Repositories\SummaryRepositoryInterface;
use App\Models\Company;

final class DailyReport
{
    public function __construct(
        private SummaryRepositoryInterface $summaries,
        private BranchRepositoryInterface $branches,
    ) {}

    /** @return array{rows: list<DailySummaryDTO>, totals: array<string, float>} */
    public function __invoke(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        if ($branchId !== null) {
            $this->branches->findForCompany($company, $branchId) ?? abort(404);
            $ids = [$branchId];
        } else {
            $ids = $company->branches()->pluck('id')->all();
        }

        $rows = array_map(
            fn ($summary) => DailySummaryDTO::fromModel($summary),
            $this->summaries->listForBranches($ids, $from, $to)
        );

        $totals = ['omzet' => 0.0, 'transactions' => 0, 'hpp' => 0.0, 'discount' => 0.0, 'tax' => 0.0, 'cash' => 0.0, 'refunds' => 0.0, 'net' => 0.0];
        foreach ($rows as $row) {
            $totals['omzet'] += (float) $row->omzet;
            $totals['transactions'] += $row->transactions;
            $totals['hpp'] += (float) $row->hppTotal;
            $totals['discount'] += (float) $row->discountTotal;
            $totals['tax'] += (float) $row->taxTotal;
            $totals['cash'] += (float) $row->cashTunai;
            $totals['refunds'] += (float) $row->refundTotal;
            $totals['net'] += (float) $row->netOmzet;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
