<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\CategoryDTO;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Models\Category;

final class UpdateCategory
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    public function __invoke(Category $category, array $attributes): CategoryDTO
    {
        return CategoryDTO::fromModel(
            $this->categories->update($category, $attributes)
        );
    }
}
