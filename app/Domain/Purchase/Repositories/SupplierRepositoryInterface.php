<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Repositories;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Supplier> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Supplier;

    public function createForCompany(Company $company, array $attributes): Supplier;

    public function update(Supplier $supplier, array $attributes): Supplier;

    public function delete(Supplier $supplier): void;
}
