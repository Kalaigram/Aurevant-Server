<?php

declare(strict_types=1);

namespace App\Domain\Service\Repositories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Customer> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Customer;

    public function createForCompany(Company $company, array $attributes): Customer;

    public function update(Customer $customer, array $attributes): Customer;

    public function delete(Customer $customer): void;
}
