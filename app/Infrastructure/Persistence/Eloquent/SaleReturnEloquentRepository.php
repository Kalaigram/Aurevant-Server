<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Sale\Repositories\SaleReturnRepositoryInterface;
use App\Models\Branch;
use App\Models\SaleReturn;
use Illuminate\Pagination\LengthAwarePaginator;

final class SaleReturnEloquentRepository implements SaleReturnRepositoryInterface
{
    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator
    {
        return SaleReturn::query()
            ->with('items')
            ->whereHas('sale', fn ($query) => $query->where('branch_id', $branch->id))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createWithItems(array $attributes, array $items): SaleReturn
    {
        $retur = SaleReturn::query()->create($attributes);
        $retur->items()->createMany($items);

        return $retur->refresh()->load('items');
    }
}
