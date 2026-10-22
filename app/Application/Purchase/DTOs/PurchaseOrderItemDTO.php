<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\PurchaseOrderItem;

final readonly class PurchaseOrderItemDTO
{
    public function __construct(
        public int $itemId,
        public ?string $itemName,
        public ?string $unit,
        public string $qtyOrdered,
        public string $qtyReceived,
        public string $unitPrice,
    ) {}

    public static function fromModel(PurchaseOrderItem $item): self
    {
        return new self(
            itemId: $item->item_id,
            itemName: $item->relationLoaded('item') ? $item->item?->name : null,
            unit: $item->relationLoaded('item') ? $item->item?->unit : null,
            qtyOrdered: (string) $item->qty_ordered,
            qtyReceived: (string) $item->qty_received,
            unitPrice: (string) $item->unit_price,
        );
    }
}
