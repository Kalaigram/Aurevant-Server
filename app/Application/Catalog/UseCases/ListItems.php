<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListItems
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    /** @return LengthAwarePaginator<int, ItemDTO> */
    public function __invoke(Company $company, int $perPage, ?string $type = null, ?int $categoryId = null): LengthAwarePaginator
    {
        return $this->items->paginateForCompany($company, $perPage, $type, $categoryId)
            ->through(fn ($item) => ItemDTO::fromModel($item));
    }
}
