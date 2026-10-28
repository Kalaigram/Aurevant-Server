<?php

declare(strict_types=1);

namespace App\Domain\Report\Repositories;

use App\Models\Company;

interface SalesPerformanceRepositoryInterface
{
    /** @return list<array{itemId: int, code: string, name: string, unit: string, qty: float, revenue: float, transactions: int, hpp: float}> */
    public function topProducts(Company $company, string $from, string $to, ?int $branchId, int $limit): array;
}
