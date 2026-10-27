<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Repositories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierReturn;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierReturnRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SupplierReturn> */
    public function paginateForOrder(PurchaseOrder $order, int $perPage): LengthAwarePaginator;

    /**
     * @param  list<int>  $receiptItemIds
     * @return array<int, float>
     */
    public function returnedQuantities(array $receiptItemIds): array;

    public function createWithItems(
        PurchaseOrder $order,
        GoodsReceipt $receipt,
        array $attributes,
        array $items,
    ): SupplierReturn;
}
