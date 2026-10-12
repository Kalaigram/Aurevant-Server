<?php

declare(strict_types=1);

namespace App\Domain\Report\Repositories;

interface SalesAggregateRepositoryInterface
{
    /** @return array{omzet: float, transactions: int, hpp: float, discount: float, tax: float, cash: float, refunds: float} */
    public function aggregateBranchDay(int $branchId, string $date): array;
}
