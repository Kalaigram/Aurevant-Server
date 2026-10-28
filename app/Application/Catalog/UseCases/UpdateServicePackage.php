<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ServicePackageDTO;
use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Models\ServicePackage;

final class UpdateServicePackage
{
    public function __construct(
        private ServicePackageRepositoryInterface $packages,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float}>  $items
     */
    public function __invoke(ServicePackage $package, array $attributes, array $items): ServicePackageDTO
    {
        return ServicePackageDTO::fromModel(
            $this->packages->update($package, $attributes, $items)
        );
    }
}
