<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Models\Company;
use App\Models\Item;
use Illuminate\Pagination\LengthAwarePaginator;

interface ItemRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Item> */
    public function paginateForCompany(Company $company, int $perPage, ?string $type = null, ?int $categoryId = null): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Item;

    public function findByCodeForCompany(Company $company, string $code): ?Item;

    public function createForCompany(Company $company, array $attributes): Item;

    public function update(Item $item, array $attributes): Item;

    public function delete(Item $item): void;
}
