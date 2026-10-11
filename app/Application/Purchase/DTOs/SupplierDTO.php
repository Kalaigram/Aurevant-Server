<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\Supplier;

final readonly class SupplierDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $name,
        public ?string $phone,
        public ?string $address,
    ) {}

    public static function fromModel(Supplier $supplier): self
    {
        return new self(
            id: $supplier->id,
            companyId: $supplier->company_id,
            name: $supplier->name,
            phone: $supplier->phone,
            address: $supplier->address,
        );
    }
}
