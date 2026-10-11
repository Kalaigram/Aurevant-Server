<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Item;

final class DeleteItem
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Item $item): void
    {
        $this->items->delete($item);
    }
}
