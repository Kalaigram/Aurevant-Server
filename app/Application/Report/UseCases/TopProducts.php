<?php

declare(strict_types=1);

namespace App\Application\Report\UseCases;

use App\Domain\Report\Repositories\SalesPerformanceRepositoryInterface;
use App\Models\Company;

final class TopProducts
{
    public function __construct(
        private SalesPerformanceRepositoryInterface $sales,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, totals: array{qty: float, revenue: float, hpp: float, margin: float}} */
    public function __invoke(
        Company $company,
        string $from,
        string $to,
        ?int $branchId = null,
        int $limit = 10,
    ): array {
        $rows = array_map(
            fn (array $row): array => $row + ['margin' => round($row['revenue'] - $row['hpp'], 2)],
            $this->sales->topProducts($company, $from, $to, $branchId, $limit),
        );

        $totals = ['qty' => 0.0, 'revenue' => 0.0, 'hpp' => 0.0, 'margin' => 0.0];
        foreach ($rows as $row) {
            $totals['qty'] += $row['qty'];
            $totals['revenue'] += $row['revenue'];
            $totals['hpp'] += $row['hpp'];
            $totals['margin'] += $row['margin'];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
