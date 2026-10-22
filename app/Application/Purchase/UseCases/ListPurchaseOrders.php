<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\PurchaseOrderDTO;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPurchaseOrders
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $orders,
    ) {}

    /** @return LengthAwarePaginator<int, PurchaseOrderDTO> */
    public function __invoke(Company $company, int $perPage, ?string $status = null, ?int $branchId = null, ?string $q = null): LengthAwarePaginator
    {
        return $this->orders->paginateForCompany($company, $perPage, $status, $branchId, $q)
            ->through(fn ($order) => PurchaseOrderDTO::fromModel($order));
    }
}
