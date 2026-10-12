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
        public string $subtotal,
        public string $discountTotal,
        public string $taxTotal,
        public string $grandTotal,
        /** @var list<SaleItemDTO> */
        public array $items,
        /** @var list<SalePaymentDTO> */
        public array $payments,
    ) {}

    public static function fromModel(Sale $sale): self
    {
        return new self(
            id: $sale->id,
            number: $sale->number,
            status: $sale->status,
            subtotal: (string) $sale->subtotal,
            discountTotal: (string) $sale->discount_total,
            taxTotal: (string) $sale->tax_total,
            grandTotal: (string) $sale->grand_total,
            items: $sale->relationLoaded('items')
                ? $sale->items->map(fn ($item) => SaleItemDTO::fromModel($item))->all()
                : [],
            payments: $sale->relationLoaded('payments')
                ? $sale->payments->map(fn ($payment) => SalePaymentDTO::fromModel($payment))->all()
                : [],
        );
    }
}
