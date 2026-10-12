<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemPrice;

interface ItemPriceRepositoryInterface
{
    public function upsert(Item $item, Branch $branch, string $price): ItemPrice;

    public function findForItemBranch(int $itemId, int $branchId): ?ItemPrice;
}
