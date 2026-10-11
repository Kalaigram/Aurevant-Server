<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Models\Branch;
use App\Models\StockBalance;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockBalanceEloquentRepository implements StockBalanceRepositoryInterface
{
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

    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator
    {
        return StockBalance::query()
            ->with('item')
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->paginate($perPage);
    }
}
