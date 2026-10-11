<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockBalance;

final readonly class StockBalanceDTO
{
    public function __construct(
        public int $itemId,
        public string $itemCode,
        public string $itemName,
        public ?string $unit,
        public string $qty,
        public string $avgCost,
    ) {}

    public static function fromModel(StockBalance $balance): self
    {
        return new self(
            itemId: $balance->item_id,
            itemCode: $balance->item->code,
            itemName: $balance->item->name,
            unit: $balance->item->unit,
            qty: (string) $balance->qty,
            avgCost: (string) $balance->avg_cost,
        );
    }
}
