<?php

declare(strict_types=1);

namespace App\Application\Catalog\DTOs;

use App\Models\ServicePackage;

final readonly class ServicePackageDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $code,
        public string $name,
        public string $price,
        public string $status,
        public int $itemCount,
        /** @var list<ServicePackageItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(ServicePackage $package): self
    {
        $items = $package->relationLoaded('items')
            ? $package->items->map(fn ($item) => ServicePackageItemDTO::fromModel($item))->all()
            : [];

        return new self(
            id: $package->id,
            companyId: $package->company_id,
            code: $package->code,
            name: $package->name,
            price: (string) $package->price,
            status: $package->status,
            itemCount: count($items),
            items: $items,
        );
    }
}
