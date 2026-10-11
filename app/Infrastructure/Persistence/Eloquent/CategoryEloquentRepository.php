<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class CategoryEloquentRepository implements CategoryRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return $company->categories()->orderBy('name')->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Category
    {
        return $company->categories()->whereKey($id)->first();
    }

    public function createForCompany(Company $company, array $attributes): Category
    {
        return $company->categories()->create($attributes)->refresh();
    }

    public function update(Category $category, array $attributes): Category
    {
        $category->update($attributes);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
