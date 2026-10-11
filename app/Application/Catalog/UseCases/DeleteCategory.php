<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Models\Category;

final class DeleteCategory
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    public function __invoke(Category $category): void
    {
        $this->categories->delete($category);
    }
}
