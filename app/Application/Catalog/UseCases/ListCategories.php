<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\CategoryDTO;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCategories
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    /** @return LengthAwarePaginator<int, CategoryDTO> */
    public function __invoke(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->categories->paginateForCompany($company, $perPage)
            ->through(fn ($category) => CategoryDTO::fromModel($category));
    }
}
