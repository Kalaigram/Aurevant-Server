<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ServicePackageDTO;
use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Models\Company;

final class CreateServicePackage
{
    public function __construct(
        private ServicePackageRepositoryInterface $packages,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float}>  $items
     */
    public function __invoke(Company $company, array $attributes, array $items): ServicePackageDTO
    {
        return ServicePackageDTO::fromModel(
            $this->packages->createForCompany($company, $attributes, $items)
        );
    }
}
