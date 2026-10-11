<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Item;

final class UpdateItem
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Item $item, array $attributes): ItemDTO
    {
        return ItemDTO::fromModel(
            $this->items->update($item, $attributes)
        );
    }
}
