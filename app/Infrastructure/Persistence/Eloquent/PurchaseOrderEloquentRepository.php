<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class PurchaseOrderEloquentRepository implements PurchaseOrderRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $status = null, ?int $branchId = null, ?string $q = null): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['items.item', 'supplier', 'branch', 'bill'])
            ->where('company_id', $company->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($inner) => $inner
                    ->where('note', 'like', $like)
                    ->when(ctype_digit($q), fn ($idQuery) => $idQuery->orWhere('id', (int) $q))
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $like)));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()
            ->with(['items.item', 'supplier', 'branch', 'bill', 'receipts.items.item'])
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

        return $order->refresh()->load(['items.item', 'supplier', 'branch', 'bill']);
    }

    public function save(PurchaseOrder $order): PurchaseOrder
    {
        $order->save();

        return $order->refresh()->load(['items.item', 'supplier', 'branch', 'bill', 'receipts.items.item']);
    }
}
