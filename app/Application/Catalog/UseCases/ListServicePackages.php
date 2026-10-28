<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ServicePackageDTO;
use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListServicePackages
{
    public function __construct(
        private ServicePackageRepositoryInterface $packages,
    ) {}

    /** @return LengthAwarePaginator<int, ServicePackageDTO> */
    public function __invoke(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator
    {
        return $this->packages->paginateForCompany($company, $perPage, $q)
            ->through(fn ($package) => ServicePackageDTO::fromModel($package));
    }
}
