<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\SaleReturnItem;

final readonly class SaleReturnItemDTO
{
    public function __construct(
        public int $saleItemId,
        public int $itemId,
        public string $qty,
        public string $condition,
        public string $refundAmount,
    ) {}

    public static function fromModel(SaleReturnItem $item): self
    {
        return new self(
            saleItemId: $item->sale_item_id,
            itemId: $item->item_id,
            qty: (string) $item->qty,
            condition: $item->condition,
            refundAmount: (string) $item->refund_amount,
        );
    }
}
