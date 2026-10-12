<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

final class SaleEloquentRepository implements SaleRepositoryInterface
{
    public function paginateForBranch(Branch $branch, int $perPage): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['items.item', 'payments'])
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForBranch(Branch $branch, int $id): ?Sale
    {
        return Sale::query()
            ->with(['items.item', 'payments'])
            ->where('branch_id', $branch->id)
            ->whereKey($id)
            ->first();
    }

    public function findByIdempotency(int $companyId, string $key): ?Sale
    {
        return Sale::query()
            ->with(['items.item', 'payments'])
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();
    }

    public function createWithRelations(array $attributes, array $items, array $payments): Sale
    {
        $sale = Sale::query()->create($attributes);
        $sale->items()->createMany($items);
        $sale->payments()->createMany($payments);

        return $sale->refresh()->load(['items.item', 'payments']);
    }
}
