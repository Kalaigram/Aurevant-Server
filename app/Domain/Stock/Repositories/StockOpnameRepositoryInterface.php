<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repositories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\StockOpname;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockOpnameRepositoryInterface
{
    /** @return LengthAwarePaginator<int, StockOpname> */
    public function paginateForBranch(Branch $branch, int $perPage, ?string $status = null): LengthAwarePaginator;

    public function findForBranch(Branch $branch, int $id): ?StockOpname;

    public function createWithItems(Company $company, Branch $branch, array $attributes, array $items): StockOpname;

    public function save(StockOpname $opname): StockOpname;
}
