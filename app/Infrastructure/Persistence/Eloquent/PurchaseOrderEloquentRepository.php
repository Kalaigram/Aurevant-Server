<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PurchaseOrderEloquentRepository implements PurchaseOrderRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $status = null, ?int $branchId = null): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['items.item', 'supplier', 'bill'])
            ->where('company_id', $company->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['items.item', 'supplier', 'bill', 'receipts.items'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createWithItems(Company $company, array $attributes, array $items): PurchaseOrder
    {
        $order = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ]);
        $order->items()->createMany($items);

        return $order->refresh()->load(['items.item', 'supplier', 'bill']);
    }

    public function save(PurchaseOrder $order): PurchaseOrder
    {
        $order->save();

        return $order->refresh()->load(['items.item', 'supplier', 'bill', 'receipts.items']);
    }
}
