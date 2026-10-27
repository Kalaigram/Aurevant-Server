<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\SupplierReturn;

final readonly class SupplierReturnDTO
{
    public function __construct(
        public int $id,
        public int $purchaseOrderId,
        public int $goodsReceiptId,
        public string $total,
        public string $reason,
        public ?string $note,
        public ?string $createdAt,
        /** @var list<SupplierReturnItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(SupplierReturn $retur): self
    {
        return new self(
            id: $retur->id,
            purchaseOrderId: $retur->purchase_order_id,
            goodsReceiptId: $retur->goods_receipt_id,
            total: (string) $retur->total,
            reason: $retur->reason,
            note: $retur->note,
            createdAt: $retur->created_at?->toIso8601String(),
            items: $retur->relationLoaded('items')
                ? $retur->items->map(fn ($item) => SupplierReturnItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
