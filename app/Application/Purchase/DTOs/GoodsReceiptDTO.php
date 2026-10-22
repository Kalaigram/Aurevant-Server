<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\GoodsReceipt;

final readonly class GoodsReceiptDTO
{
    public function __construct(
        public int $id,
        public int $purchaseOrderId,
        public ?string $dueAt,
        public ?string $note,
        public ?string $createdAt,
        /** @var list<GoodsReceiptItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(GoodsReceipt $receipt): self
    {
        return new self(
            id: $receipt->id,
            purchaseOrderId: $receipt->purchase_order_id,
            dueAt: $receipt->due_at?->toDateString(),
            note: $receipt->note,
            createdAt: $receipt->created_at?->toIso8601String(),
            items: $receipt->relationLoaded('items')
                ? $receipt->items->map(fn ($item) => GoodsReceiptItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
