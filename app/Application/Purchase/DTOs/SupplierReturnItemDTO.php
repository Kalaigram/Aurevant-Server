<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\SupplierReturnItem;

final readonly class SupplierReturnItemDTO
{
    public function __construct(
        public int $id,
        public int $goodsReceiptItemId,
        public int $itemId,
        public ?string $itemName,
        public ?string $unit,
        public string $qty,
        public string $unitCost,
        public string $subtotal,
    ) {}

    public static function fromModel(SupplierReturnItem $item): self
    {
        return new self(
            id: $item->id,
            goodsReceiptItemId: $item->goods_receipt_item_id,
            itemId: $item->item_id,
            itemName: $item->relationLoaded('item') ? $item->item?->name : null,
            unit: $item->relationLoaded('item') ? $item->item?->unit : null,
            qty: (string) $item->qty,
            unitCost: (string) $item->unit_cost,
            subtotal: (string) $item->subtotal,
        );
    }
}
