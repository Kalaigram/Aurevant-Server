<?php

declare(strict_types=1);

namespace App\Application\Service\DTOs;

use App\Models\ServiceOrderItem;

final readonly class ServiceOrderItemDTO
{
    public function __construct(
        public int $itemId,
        public string $qty,
        public string $price,
        public string $commissionAmount,
    ) {}

    public static function fromModel(ServiceOrderItem $item): self
    {
        return new self(
            itemId: $item->item_id,
            qty: (string) $item->qty,
            price: (string) $item->price,
            commissionAmount: (string) $item->commission_amount,
        );
    }
}
