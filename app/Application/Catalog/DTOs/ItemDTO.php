<?php

declare(strict_types=1);

namespace App\Application\Catalog\DTOs;

use App\Models\Item;

final readonly class ItemDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public ?int $categoryId,
        public string $code,
        public string $name,
        public string $type,
        public ?string $unit,
        public bool $trackStock,
        public ?int $durationMinutes,
        public string $status,
        /** @var list<ItemPriceDTO> */
        public array $prices = [],
    ) {}

    public static function fromModel(Item $item): self
    {
        return new self(
            id: $item->id,
            companyId: $item->company_id,
            categoryId: $item->category_id,
            code: $item->code,
            name: $item->name,
            type: $item->type,
            unit: $item->unit,
            trackStock: (bool) $item->track_stock,
            durationMinutes: $item->duration_minutes,
            status: $item->status,
            prices: $item->relationLoaded('prices')
                ? $item->prices->map(fn ($price) => ItemPriceDTO::fromModel($price))->all()
                : [],
        );
    }
}
