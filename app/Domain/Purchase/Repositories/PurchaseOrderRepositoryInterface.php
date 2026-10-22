<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Repositories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseOrderRepositoryInterface
{
    /** @return LengthAwarePaginator<int, PurchaseOrder> */
    public function paginateForCompany(Company $company, int $perPage, ?string $status = null, ?int $branchId = null, ?string $q = null): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?PurchaseOrder;

    public function createWithItems(Company $company, array $attributes, array $items): PurchaseOrder;

    public function save(PurchaseOrder $order): PurchaseOrder;
}
