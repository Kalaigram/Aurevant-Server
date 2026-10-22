<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockOpname;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockOpnameEloquentRepository implements StockOpnameRepositoryInterface
{
    public function paginateForBranch(Branch $branch, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        return StockOpname::query()
            ->with(['items.item', 'branch'])
            ->where('branch_id', $branch->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForBranch(Branch $branch, int $id): ?StockOpname
    {
        return StockOpname::query()
            ->with(['items.item', 'branch'])
            ->where('branch_id', $branch->id)
            ->whereKey($id)
            ->first();
    }

    public function createWithItems(Company $company, Branch $branch, array $attributes, array $items): StockOpname
    {
        $opname = StockOpname::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            ...$attributes,
        ]);
        $opname->items()->createMany($items);

        return $opname->refresh()->load(['items.item', 'branch']);
    }

    public function save(StockOpname $opname): StockOpname
    {
        $opname->save();

        return $opname->refresh()->load(['items.item', 'branch']);
    }
}
