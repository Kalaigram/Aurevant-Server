<?php

declare(strict_types=1);

namespace App\Application\Service\DTOs;

use App\Models\Customer;

final readonly class CustomerDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $name,
        public ?string $phone,
        public ?string $address,
        public ?string $notes,
    ) {}

    public static function fromModel(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            companyId: $customer->company_id,
            name: $customer->name,
            phone: $customer->phone,
            address: $customer->address,
            notes: $customer->notes,
        );
    }
}
