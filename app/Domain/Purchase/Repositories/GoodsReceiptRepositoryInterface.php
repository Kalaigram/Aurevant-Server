<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Repositories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

interface GoodsReceiptRepositoryInterface
{
    /** @return LengthAwarePaginator<int, GoodsReceipt> */
    public function paginateForOrder(PurchaseOrder $order, int $perPage): LengthAwarePaginator;

    public function findForOrder(PurchaseOrder $order, int $id, bool $lock = false): ?GoodsReceipt;

    public function createWithItems(PurchaseOrder $order, array $attributes, array $items): GoodsReceipt;
}
