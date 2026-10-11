<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockOpnameItem;

final readonly class StockOpnameItemDTO
{
    public function __construct(
        public int $itemId,
        public string $qtySystem,
        public string $qtyCounted,
        public string $diff,
    ) {}

    public static function fromModel(StockOpnameItem $item): self
    {
        return new self(
            itemId: $item->item_id,
            qtySystem: (string) $item->qty_system,
            qtyCounted: (string) $item->qty_counted,
            diff: (string) ((float) $item->qty_counted - (float) $item->qty_system),
        );
    }
}
