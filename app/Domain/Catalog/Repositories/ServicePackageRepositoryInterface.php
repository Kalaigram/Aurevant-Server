<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Repositories;

use App\Models\Company;
use App\Models\ServicePackage;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServicePackageRepositoryInterface
{
    /** @return LengthAwarePaginator<int, ServicePackage> */
    public function paginateForCompany(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?ServicePackage;

    public function createForCompany(Company $company, array $attributes, array $items): ServicePackage;

    public function update(ServicePackage $package, array $attributes, array $items): ServicePackage;

    public function delete(ServicePackage $package): void;
}
