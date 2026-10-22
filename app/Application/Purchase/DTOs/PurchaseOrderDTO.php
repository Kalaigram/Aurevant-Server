<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\PurchaseOrder;

final readonly class PurchaseOrderDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public ?string $branchName,
        public int $supplierId,
        public ?string $supplierName,
        public ?string $supplierAddress,
        public string $status,
        public ?string $note,
        public ?string $createdAt,
        public string $total,
        /** @var list<PurchaseOrderItemDTO> */
        public array $items,
        public ?SupplierBillDTO $bill,
    ) {}

    public static function fromModel(PurchaseOrder $order): self
    {
        $items = $order->relationLoaded('items')
            ? $order->items->map(fn ($item) => PurchaseOrderItemDTO::fromModel($item))->all()
            : [];

        $total = 0.0;
        foreach ($order->relationLoaded('items') ? $order->items : [] as $item) {
            $total += (float) $item->qty_ordered * (float) $item->unit_price;
        }

        return new self(
            id: $order->id,
            branchId: $order->branch_id,
            branchName: $order->relationLoaded('branch') ? $order->branch?->name : null,
            supplierId: $order->supplier_id,
            supplierName: $order->relationLoaded('supplier') ? $order->supplier?->name : null,
            supplierAddress: $order->relationLoaded('supplier') ? $order->supplier?->address : null,
            status: $order->status,
            note: $order->note,
            createdAt: $order->created_at?->toIso8601String(),
            total: number_format($total, 2, '.', ''),
            items: $items,
            bill: $order->relationLoaded('bill') && $order->bill !== null
                ? SupplierBillDTO::fromModel($order->bill)
                : null,
        );
    }
}
