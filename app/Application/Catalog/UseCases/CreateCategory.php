<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\CategoryDTO;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Models\Company;

final class CreateCategory
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    public function __invoke(Company $company, array $attributes): CategoryDTO
    {
        return CategoryDTO::fromModel(
            $this->categories->createForCompany($company, $attributes)
        );
    }
}
