<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Finance\Repositories\DebtRepositoryInterface;
use App\Models\Company;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use Illuminate\Pagination\LengthAwarePaginator;

final class DebtEloquentRepository implements DebtRepositoryInterface
{
    public function paginateBills(Company $company, int $perPage, bool $unpaidOnly = false): LengthAwarePaginator
    {
        return SupplierBill::query()
            ->with(['order.supplier', 'payments'])
            ->where('company_id', $company->id)
            ->when($unpaidOnly, fn ($query) => $query->whereColumn('paid_amount', '<', 'amount'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findBill(Company $company, int $id): ?SupplierBill
    {
        return SupplierBill::query()
            ->with(['order.supplier', 'payments'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function payBill(SupplierBill $bill, array $attributes): SupplierBillPayment
    {
        $payment = SupplierBillPayment::query()->create([
            'supplier_bill_id' => $bill->id,
            ...$attributes,
        ]);

        $bill->paid_amount = (float) $bill->paid_amount + (float) $payment->amount;
        $bill->save();

        return $payment->refresh();
    }

    public function unpaidBills(Company $company): array
    {
        return SupplierBill::query()
            ->with('order.supplier')
            ->where('company_id', $company->id)
            ->whereColumn('paid_amount', '<', 'amount')
            ->orderBy('due_at')
            ->get()
            ->all();
    }

    public function paginateReceivables(Company $company, int $perPage, bool $unpaidOnly = false): LengthAwarePaginator
    {
        return Receivable::query()
            ->with(['customer', 'payments'])
            ->where('company_id', $company->id)
            ->when($unpaidOnly, fn ($query) => $query->whereColumn('paid_amount', '<', 'amount'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findReceivable(Company $company, int $id): ?Receivable
    {
        return Receivable::query()
            ->with(['customer', 'payments'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createReceivable(Company $company, array $attributes): Receivable
    {
        return Receivable::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh()->load(['customer', 'payments']);
    }

    public function payReceivable(Receivable $receivable, array $attributes): ReceivablePayment
    {
        $payment = ReceivablePayment::query()->create([
            'receivable_id' => $receivable->id,
            ...$attributes,
        ]);

        $receivable->paid_amount = (float) $receivable->paid_amount + (float) $payment->amount;
        $receivable->save();

        return $payment->refresh();
    }

    public function unpaidReceivables(Company $company): array
    {
        return Receivable::query()
            ->with('customer')
            ->where('company_id', $company->id)
            ->whereColumn('paid_amount', '<', 'amount')
            ->orderBy('due_at')
            ->get()
            ->all();
    }
}
