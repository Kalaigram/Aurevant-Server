<?php

declare(strict_types=1);

namespace App\Domain\Sale\Repositories;

use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

interface SaleRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Sale> */
    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator;

    public function findForBranch(Branch $branch, int $id): ?Sale;

    public function findByIdempotency(int $companyId, string $key): ?Sale;

    public function createWithRelations(array $attributes, array $items, array $payments): Sale;
}
