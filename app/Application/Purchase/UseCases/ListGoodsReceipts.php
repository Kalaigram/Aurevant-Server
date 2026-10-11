<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\GoodsReceiptDTO;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListGoodsReceipts
{
    public function __construct(
        private GoodsReceiptRepositoryInterface $receipts,
    ) {}

    /** @return LengthAwarePaginator<int, GoodsReceiptDTO> */
    public function __invoke(PurchaseOrder $order, int $perPage): LengthAwarePaginator
    {
        return $this->receipts->paginateForOrder($order, $perPage)
            ->through(fn ($receipt) => GoodsReceiptDTO::fromModel($receipt));
    }
}
