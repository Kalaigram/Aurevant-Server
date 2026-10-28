<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Models\ServicePackage;

final class DeleteServicePackage
{
    public function __construct(
        private ServicePackageRepositoryInterface $packages,
    ) {}

    public function __invoke(ServicePackage $package): void
    {
        $this->packages->delete($package);
    }
}
