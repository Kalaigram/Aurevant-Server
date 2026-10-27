<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\GoodsReceiptItem;

final readonly class GoodsReceiptItemDTO
{
    public function __construct(
        public int $id,
        public int $itemId,
        public ?string $itemName,
        public ?string $unit,
        public string $qtyReceived,
        public string $qtyReturned,
        public string $unitCost,
    ) {}

    public static function fromModel(GoodsReceiptItem $item): self
    {
        return new self(
            id: $item->id,
            itemId: $item->item_id,
            itemName: $item->relationLoaded('item') ? $item->item?->name : null,
            unit: $item->relationLoaded('item') ? $item->item?->unit : null,
            qtyReceived: (string) $item->qty_received,
            qtyReturned: number_format(
                (float) ($item->relationLoaded('returnItems') ? $item->returnItems->sum('qty') : 0),
                3,
                '.',
                '',
            ),
            unitCost: (string) $item->unit_cost,
        );
    }
}
