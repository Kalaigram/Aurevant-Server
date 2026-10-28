<?php

declare(strict_types=1);

namespace App\Application\Catalog\DTOs;

use App\Models\ServicePackageItem;

final readonly class ServicePackageItemDTO
{
    public function __construct(
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?string $unit,
        public string $qty,
    ) {}

    public static function fromModel(ServicePackageItem $item): self
    {
        $katalog = $item->relationLoaded('item') ? $item->item : null;

        return new self(
            itemId: $item->item_id,
            itemCode: $katalog?->code,
            itemName: $katalog?->name,
            unit: $katalog?->unit,
            qty: (string) $item->qty,
        );
    }
}
