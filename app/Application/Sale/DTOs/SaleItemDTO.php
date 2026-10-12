<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\SaleItem;

final readonly class SaleItemDTO
{
    public function __construct(
        public int $itemId,
        public string $qty,
        public string $price,
        public string $discountPercent,
        public string $discountAmount,
        public string $taxPercent,
        public string $lineTotal,
        public ?string $hppAmount,
    ) {}

    public static function fromModel(SaleItem $item): self
    {
        $str = fn ($value) => $value === null ? null : (string) $value;

        return new self(
            itemId: $item->item_id,
            qty: (string) $item->qty,
            price: (string) $item->price,
            discountPercent: (string) $item->discount_percent,
            discountAmount: (string) $item->discount_amount,
            taxPercent: (string) $item->tax_percent,
            lineTotal: (string) $item->line_total,
            hppAmount: $str($item->hpp_amount),
        );
    }
}
