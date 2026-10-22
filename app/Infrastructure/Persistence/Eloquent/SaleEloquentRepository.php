<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Pagination\LengthAwarePaginator;

final class SaleEloquentRepository implements SaleRepositoryInterface
{
    public function paginateForBranch(Branch $branch, int $perPage, array $filters = []): LengthAwarePaginator
    {
        return Sale::query()
            ->with(['items.item', 'payments', 'user:id,name', 'customer:id,name'])
            ->where('branch_id', $branch->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $like = '%'.$term.'%';
                $query->where(fn ($inner) => $inner
                    ->where('number', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like)));
            })
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForBranch(Branch $branch, int $id): ?Sale
    {
        return Sale::query()
            ->with(['items.item', 'payments', 'user:id,name', 'customer:id,name', 'returns.items'])
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
