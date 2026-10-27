<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\SupplierReturnDTO;
use App\Domain\Purchase\Repositories\SupplierReturnRepositoryInterface;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListSupplierReturns
{
    public function __construct(
        private SupplierReturnRepositoryInterface $returns,
    ) {}

    /** @return LengthAwarePaginator<int, SupplierReturnDTO> */
    public function __invoke(PurchaseOrder $order, int $perPage): LengthAwarePaginator
    {
        return $this->returns->paginateForOrder($order, $perPage)
            ->through(fn ($retur) => SupplierReturnDTO::fromModel($retur));
    }
}
