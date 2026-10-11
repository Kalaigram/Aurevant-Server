<?php

declare(strict_types=1);

namespace App\Application\Catalog\DTOs;

use App\Models\Category;

final readonly class CategoryDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $name,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->id,
            companyId: $category->company_id,
            name: $category->name,
        );
    }
}
