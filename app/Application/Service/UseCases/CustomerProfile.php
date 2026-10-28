<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\CustomerDTO;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Company;
use App\Models\Customer;

final class CustomerProfile
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, Customer $customer): array
    {
        $profile = $this->customers->profileForCompany($company, $customer);
        $profile['customer'] = CustomerDTO::fromModel($profile['customer']);

        return $profile;
    }
}
