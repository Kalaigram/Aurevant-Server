<?php

declare(strict_types=1);

namespace App\Application\Report\UseCases;

use App\Domain\Report\Repositories\SalesAggregateRepositoryInterface;
use App\Domain\Report\Repositories\SummaryRepositoryInterface;
use App\Models\Branch;

final class RebuildDailySummary
{
    public function __construct(
        private SalesAggregateRepositoryInterface $aggregates,
        private SummaryRepositoryInterface $summaries,
    ) {}

    public function __invoke(Branch $branch, string $date): void
    {
        $agg = $this->aggregates->aggregateBranchDay($branch->id, $date);

        $this->summaries->upsert($branch, $date, [
            'omzet' => $agg['omzet'],
            'transactions' => $agg['transactions'],
            'hpp_total' => $agg['hpp'],
            'discount_total' => $agg['discount'],
            'tax_total' => $agg['tax'],
            'cash_tunai' => $agg['cash'],
            'refund_total' => $agg['refunds'],
            'net_omzet' => $agg['omzet'] - $agg['refunds'],
        ]);
    }
}
