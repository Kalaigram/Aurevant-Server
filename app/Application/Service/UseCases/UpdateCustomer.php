<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\CustomerDTO;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;

final class UpdateCustomer
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
    ) {}

    public function __invoke(Customer $customer, array $attributes): CustomerDTO
    {
        return CustomerDTO::fromModel(
            $this->customers->update($customer, $attributes)
        );
    }
}
