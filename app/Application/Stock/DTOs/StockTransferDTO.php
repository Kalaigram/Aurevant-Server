<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockTransfer;

final readonly class StockTransferDTO
{
    public function __construct(
        public int $id,
        public int $fromBranchId,
        public int $toBranchId,
        public string $status,
        public ?string $note,
        /** @var list<StockTransferItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(StockTransfer $transfer): self
    {
        return new self(
            id: $transfer->id,
            fromBranchId: $transfer->from_branch_id,
            toBranchId: $transfer->to_branch_id,
            status: $transfer->status,
            note: $transfer->note,
            items: $transfer->relationLoaded('items')
                ? $transfer->items->map(fn ($item) => StockTransferItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
