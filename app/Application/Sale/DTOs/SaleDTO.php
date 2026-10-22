<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\Sale;

final readonly class SaleDTO
{
    public function __construct(
        public int $id,
        public string $number,
        public string $status,
        public ?string $createdAt,
        public ?string $cashierName,
        public ?string $customerName,
        public string $subtotal,
        public string $discountTotal,
        public string $taxTotal,
        public string $grandTotal,
        /** @var list<SaleItemDTO> */
        public array $items,
        /** @var list<SalePaymentDTO> */
        public array $payments,
        /** @var list<SaleReturnDTO> */
        public array $returns,
    ) {}

    public static function fromModel(Sale $sale): self
    {
        $returned = [];
        if ($sale->relationLoaded('returns')) {
            foreach ($sale->returns as $retur) {
                foreach ($retur->items as $item) {
                    $returned[$item->sale_item_id] =
                        ($returned[$item->sale_item_id] ?? 0) + (float) $item->qty;
                }
            }
        }

        return new self(
            id: $sale->id,
            number: $sale->number,
            status: $sale->status,
            createdAt: $sale->created_at?->toIso8601String(),
            cashierName: $sale->relationLoaded('user') ? $sale->user?->name : null,
            customerName: $sale->relationLoaded('customer') ? $sale->customer?->name : null,
            subtotal: (string) $sale->subtotal,
            discountTotal: (string) $sale->discount_total,
            taxTotal: (string) $sale->tax_total,
            grandTotal: (string) $sale->grand_total,
            items: $sale->relationLoaded('items')
                ? $sale->items->map(fn ($item) => SaleItemDTO::fromModel(
                    $item,
                    $sale->relationLoaded('returns') ? ($returned[$item->id] ?? 0.0) : null,
                ))->all()
                : [],
            payments: $sale->relationLoaded('payments')
                ? $sale->payments->map(fn ($payment) => SalePaymentDTO::fromModel($payment))->all()
                : [],
            returns: $sale->relationLoaded('returns')
                ? $sale->returns->map(fn ($retur) => SaleReturnDTO::fromModel($retur))->all()
                : [],
        );
    }
}
