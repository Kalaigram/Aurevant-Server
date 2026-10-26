<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Models\Branch;
use App\Models\StockBalance;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockBalanceEloquentRepository implements StockBalanceRepositoryInterface
{
    public function find(int $branchId, int $itemId): ?StockBalance
    {
        return StockBalance::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->first();
    }

    public function findLocked(int $branchId, int $itemId): ?StockBalance
    {
        return StockBalance::query()
            ->where('branch_id', $branchId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $attributes): StockBalance
    {
        return StockBalance::query()->create($attributes)->refresh();
    }

    public function save(StockBalance $balance): StockBalance
    {
        $balance->save();

        return $balance->refresh();
    }

    public function paginateForBranch(Branch $branch, int $perPage, ?string $q = null, bool $lowStock = false): LengthAwarePaginator
    {
        return StockBalance::query()
            ->with('item')
            ->where('branch_id', $branch->id)
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->whereHas('item', fn ($item) => $item
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like));
            })
            ->when($lowStock, fn ($query) => $query
                ->whereNotNull('min_stock')
                ->whereColumn('qty', '<=', 'min_stock'))
            ->orderBy('id')
            ->paginate($perPage);
    }
}
