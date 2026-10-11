<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repositories;

use App\Models\Branch;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockMovementRepositoryInterface
{
    public function record(array $attributes): StockMovement;

    /** @return LengthAwarePaginator<int, StockMovement> */
    public function historyForBranch(Branch $branch, int $perPage, ?int $itemId = null): LengthAwarePaginator;
}
