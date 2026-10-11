<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemPriceDTO;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Models\Branch;
use App\Models\Item;

final class SetItemPrice
{
    public function __construct(
        private ItemPriceRepositoryInterface $prices,
    ) {}

    public function __invoke(Item $item, Branch $branch, string $price): ItemPriceDTO
    {
        return ItemPriceDTO::fromModel(
            $this->prices->upsert($item, $branch, $price)
        );
    }
}
