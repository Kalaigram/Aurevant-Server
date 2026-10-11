<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\PurchaseOrder;

final readonly class PurchaseOrderDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public int $supplierId,
        public string $status,
        public ?string $note,
        /** @var list<PurchaseOrderItemDTO> */
        public array $items,
        public ?SupplierBillDTO $bill,
    ) {}

    public static function fromModel(PurchaseOrder $order): self
    {
        return new self(
            id: $order->id,
            branchId: $order->branch_id,
            supplierId: $order->supplier_id,
            status: $order->status,
            note: $order->note,
            items: $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => PurchaseOrderItemDTO::fromModel($item))->all()
                : [],
            bill: $order->relationLoaded('bill') && $order->bill !== null
                ? SupplierBillDTO::fromModel($order->bill)
                : null,
        );
    }
}
