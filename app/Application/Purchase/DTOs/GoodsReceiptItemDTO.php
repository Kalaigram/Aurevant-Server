<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\GoodsReceiptItem;

final readonly class GoodsReceiptItemDTO
{
    public function __construct(
        public int $itemId,
        public string $qtyReceived,
        public string $unitCost,
    ) {}

    public static function fromModel(GoodsReceiptItem $item): self
    {
        return new self(
            itemId: $item->item_id,
            qtyReceived: (string) $item->qty_received,
            unitCost: (string) $item->unit_cost,
        );
    }
}
