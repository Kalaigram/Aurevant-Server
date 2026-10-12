<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemPrice;

final class ItemPriceEloquentRepository implements ItemPriceRepositoryInterface
{
    public function upsert(Item $item, Branch $branch, string $price): ItemPrice
    {
        return ItemPrice::query()->updateOrCreate(
            ['item_id' => $item->id, 'branch_id' => $branch->id],
            ['price' => $price]
        );
    }

    public function findForItemBranch(int $itemId, int $branchId): ?ItemPrice
    {
        return ItemPrice::query()
            ->where('item_id', $itemId)
            ->where('branch_id', $branchId)
            ->first();
    }
}
