<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repositories;

use App\Models\Branch;
use App\Models\StockBalance;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockBalanceRepositoryInterface
{
    public function findLocked(int $branchId, int $itemId): ?StockBalance;

    public function create(array $attributes): StockBalance;

    public function save(StockBalance $balance): StockBalance;

    /** @return LengthAwarePaginator<int, StockBalance> */
    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator;
}
