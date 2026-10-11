<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Repositories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;

interface SupplierBillRepositoryInterface
{
    public function findOrCreateForOrder(Company $company, PurchaseOrder $order): SupplierBill;

    public function save(SupplierBill $bill): SupplierBill;
}
