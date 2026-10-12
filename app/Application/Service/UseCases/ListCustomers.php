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
    public function __invoke(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->customers->paginateForCompany($company, $perPage)
            ->through(fn ($customer) => CustomerDTO::fromModel($customer));
    }
}
