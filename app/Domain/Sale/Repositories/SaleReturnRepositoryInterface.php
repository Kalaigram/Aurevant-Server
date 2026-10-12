<?php

declare(strict_types=1);

namespace App\Domain\Sale\Repositories;

use App\Models\Branch;
use App\Models\SaleReturn;
use Illuminate\Pagination\LengthAwarePaginator;

interface SaleReturnRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SaleReturn> */
    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator;

    public function createWithItems(array $attributes, array $items): SaleReturn;
}
