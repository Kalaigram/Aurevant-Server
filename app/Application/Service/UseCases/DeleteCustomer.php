<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;

final class DeleteCustomer
{
    public function __construct(
        private CustomerRepositoryInterface $customers,
    ) {}

    public function __invoke(Customer $customer): void
    {
        $this->customers->delete($customer);
    }
}
