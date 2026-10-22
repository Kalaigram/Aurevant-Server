<?php

declare(strict_types=1);

namespace App\Application\Service\DTOs;

use App\Models\ServiceOrderItem;

final readonly class ServiceOrderItemDTO
{
    public function __construct(
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?string $unit,
        public string $qty,
        public string $price,
        public string $commissionAmount,
    ) {}

    public static function fromModel(ServiceOrderItem $item): self
    {
        $katalog = $item->relationLoaded('item') ? $item->item : null;

        return new self(
            itemId: $item->item_id,
            itemCode: $katalog?->code,
            itemName: $katalog?->name,
            unit: $katalog?->unit,
            qty: (string) $item->qty,
            price: (string) $item->price,
            commissionAmount: (string) $item->commission_amount,
        );
    }
}
