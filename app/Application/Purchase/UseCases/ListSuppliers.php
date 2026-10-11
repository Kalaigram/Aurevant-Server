<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\SupplierDTO;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListSuppliers
{
    public function __construct(
        private SupplierRepositoryInterface $suppliers,
    ) {}

    /** @return LengthAwarePaginator<int, SupplierDTO> */
    public function __invoke(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->suppliers->paginateForCompany($company, $perPage)
            ->through(fn ($supplier) => SupplierDTO::fromModel($supplier));
    }
}
