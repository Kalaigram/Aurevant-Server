<?php

declare(strict_types=1);

namespace App\Application\Stock\DTOs;

use App\Models\StockTransfer;

final readonly class StockTransferDTO
{
    public function __construct(
        public int $id,
        public int $fromBranchId,
        public ?string $fromBranchName,
        public ?string $fromBranchCode,
        public int $toBranchId,
        public ?string $toBranchName,
        public ?string $toBranchCode,
        public string $status,
        public ?string $note,
        public ?string $createdAt,
        public ?string $shippedAt,
        public ?string $receivedAt,
        /** @var list<StockTransferItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(StockTransfer $transfer): self
    {
        return new self(
            id: $transfer->id,
            fromBranchId: $transfer->from_branch_id,
            fromBranchName: $transfer->relationLoaded('fromBranch') ? $transfer->fromBranch?->name : null,
            fromBranchCode: $transfer->relationLoaded('fromBranch') ? $transfer->fromBranch?->code : null,
            toBranchId: $transfer->to_branch_id,
            toBranchName: $transfer->relationLoaded('toBranch') ? $transfer->toBranch?->name : null,
            toBranchCode: $transfer->relationLoaded('toBranch') ? $transfer->toBranch?->code : null,
            status: $transfer->status,
            note: $transfer->note,
            createdAt: $transfer->created_at?->toIso8601String(),
            shippedAt: $transfer->shipped_at?->toIso8601String(),
            receivedAt: $transfer->received_at?->toIso8601String(),
            items: $transfer->relationLoaded('items')
                ? $transfer->items->map(fn ($item) => StockTransferItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
