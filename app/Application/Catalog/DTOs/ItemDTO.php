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
        public ?string $categoryName,
        public string $code,
        public string $name,
        public string $type,
        public ?string $unit,
        public ?string $imagePath,
        public ?string $imageUrl,
        public bool $trackStock,
        public bool $hasVariants,
        public bool $trackBatch,
        public bool $trackSerial,
        public ?int $durationMinutes,
        public ?string $commissionType,
        public ?string $commissionValue,
        public string $status,
        /** @var list<ItemPriceDTO> */
        public array $prices = [],
    ) {}

    public static function fromModel(Item $item): self
    {
        $imagePath = $item->image_path;

        return new self(
            id: $item->id,
            companyId: $item->company_id,
            categoryId: $item->category_id,
            categoryName: $item->relationLoaded('category') ? $item->category?->name : null,
            code: $item->code,
            name: $item->name,
            type: $item->type,
            unit: $item->unit,
            imagePath: $imagePath,
            imageUrl: $imagePath !== null ? '/storage/'.ltrim($imagePath, '/') : null,
            trackStock: (bool) $item->track_stock,
            hasVariants: (bool) $item->has_variants,
            trackBatch: (bool) $item->track_batch,
            trackSerial: (bool) $item->track_serial,
            durationMinutes: $item->duration_minutes,
            commissionType: $item->commission_type,
            commissionValue: $item->commission_value !== null ? (string) $item->commission_value : null,
            status: $item->status,
            prices: $item->relationLoaded('prices')
                ? $item->prices->map(fn ($price) => ItemPriceDTO::fromModel($price))->all()
                : [],
        );
    }
}
