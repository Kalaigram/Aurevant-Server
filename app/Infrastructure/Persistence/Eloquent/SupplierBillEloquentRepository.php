<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\SupplierBillRepositoryInterface;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;

final class SupplierBillEloquentRepository implements SupplierBillRepositoryInterface
{
    public function findOrCreateForOrder(Company $company, PurchaseOrder $order): SupplierBill
    {
        return SupplierBill::query()->firstOrCreate(
            ['purchase_order_id' => $order->id],
            ['company_id' => $company->id, 'amount' => 0, 'paid_amount' => 0]
        );
    }

    public function findForOrder(Company $company, PurchaseOrder $order, bool $lock = false): ?SupplierBill
    {
        return SupplierBill::query()
            ->where('company_id', $company->id)
            ->where('purchase_order_id', $order->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function save(SupplierBill $bill): SupplierBill
    {
        $bill->save();

        return $bill->refresh();
    }
}
