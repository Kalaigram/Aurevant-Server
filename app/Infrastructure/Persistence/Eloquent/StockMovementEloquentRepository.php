<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Models\Branch;
use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockMovementEloquentRepository implements StockMovementRepositoryInterface
{
    public function record(array $attributes): StockMovement
    {
        return StockMovement::query()->create($attributes)->refresh();
    }

    public function historyForBranch(Branch $branch, int $perPage, ?int $itemId = null, ?string $q = null): LengthAwarePaginator
    {
        return StockMovement::query()
            ->with('item')
            ->where('branch_id', $branch->id)
            ->when($itemId !== null, fn ($query) => $query->where('item_id', $itemId))
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($inner) => $inner
                    ->where('note', 'like', $like)
                    ->orWhereHas('item', fn ($item) => $item
                        ->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
