<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockTransferItem;

final readonly class StockTransferItemDTO
{
    public function __construct(
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?string $unit,
        public string $qtyRequested,
        public ?string $qtyShipped,
        public ?string $qtyReceived,
        public ?string $costPerUnit,
    ) {}

    public static function fromModel(StockTransferItem $item): self
    {
        $str = fn ($value) => $value === null ? null : (string) $value;

        return new self(
            itemId: $item->item_id,
            itemCode: $item->relationLoaded('item') ? $item->item?->code : null,
            itemName: $item->relationLoaded('item') ? $item->item?->name : null,
            unit: $item->relationLoaded('item') ? $item->item?->unit : null,
            qtyRequested: (string) $item->qty_requested,
            qtyShipped: $str($item->qty_shipped),
            qtyReceived: $str($item->qty_received),
            costPerUnit: $str($item->cost_per_unit),
        );
    }
}
