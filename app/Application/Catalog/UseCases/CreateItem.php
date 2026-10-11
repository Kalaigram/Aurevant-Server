<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Company;

final class CreateItem
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Company $company, array $attributes): ItemDTO
    {
        return ItemDTO::fromModel(
            $this->items->createForCompany($company, $attributes)->load('prices')
        );
    }
}
