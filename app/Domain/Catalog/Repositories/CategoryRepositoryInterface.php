<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Category> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Category;

    public function createForCompany(Company $company, array $attributes): Category;

    public function update(Category $category, array $attributes): Category;

    public function delete(Category $category): void;
}
