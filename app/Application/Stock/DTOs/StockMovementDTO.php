<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockMovement;

final readonly class StockMovementDTO
{
    public function __construct(
        public int $id,
        public int $itemId,
        public string $type,
        public string $qty,
        public ?string $unitCost,
        public ?string $hppAmount,
        public string $balanceAfter,
        public ?string $note,
        public string $createdAt,
    ) {}

    public static function fromModel(StockMovement $movement): self
    {
        return new self(
            id: $movement->id,
            itemId: $movement->item_id,
            type: $movement->type,
            qty: (string) $movement->qty,
            unitCost: $movement->unit_cost === null ? null : (string) $movement->unit_cost,
            hppAmount: $movement->hpp_amount === null ? null : (string) $movement->hpp_amount,
            balanceAfter: (string) $movement->balance_after,
            note: $movement->note,
            createdAt: $movement->created_at->toISOString(),
        );
    }
}
