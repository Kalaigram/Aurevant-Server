<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\Company;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use Illuminate\Pagination\LengthAwarePaginator;

interface DebtRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SupplierBill> */
    public function paginateBills(Company $company, int $perPage, bool $unpaidOnly = false): LengthAwarePaginator;

    public function findBill(Company $company, int $id): ?SupplierBill;

    public function payBill(SupplierBill $bill, array $attributes): SupplierBillPayment;

    /** @return list<SupplierBill> */
    public function unpaidBills(Company $company): array;

    /** @return LengthAwarePaginator<int, Receivable> */
    public function paginateReceivables(Company $company, int $perPage, bool $unpaidOnly = false): LengthAwarePaginator;

    public function findReceivable(Company $company, int $id): ?Receivable;

    public function createReceivable(Company $company, array $attributes): Receivable;

    public function payReceivable(Receivable $receivable, array $attributes): ReceivablePayment;

    /** @return list<Receivable> */
    public function unpaidReceivables(Company $company): array;
}
