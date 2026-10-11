<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockOpname;

final readonly class StockOpnameDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public string $status,
        public ?string $note,
        /** @var list<StockOpnameItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(StockOpname $opname): self
    {
        return new self(
            id: $opname->id,
            branchId: $opname->branch_id,
            status: $opname->status,
            note: $opname->note,
            items: $opname->relationLoaded('items')
                ? $opname->items->map(fn ($item) => StockOpnameItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
