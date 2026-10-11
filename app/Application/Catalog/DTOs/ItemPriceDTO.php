<?php

declare(strict_types=1);

namespace App\Application\Catalog\DTOs;

use App\Models\ItemPrice;

final readonly class ItemPriceDTO
{
    public function __construct(
        public int $branchId,
        public string $price,
    ) {}

    public static function fromModel(ItemPrice $price): self
    {
        return new self(
            branchId: $price->branch_id,
            price: (string) $price->price,
        );
    }
}
