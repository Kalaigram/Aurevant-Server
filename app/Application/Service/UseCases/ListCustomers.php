<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\CustomerDTO;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCustomers
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
    ) {}

    /** @return LengthAwarePaginator<int, CustomerDTO> */
    public function __invoke(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator
    {
        return $this->customers->paginateForCompany($company, $perPage, $q)
            ->through(fn ($customer) => CustomerDTO::fromModel($customer));
    }
}
