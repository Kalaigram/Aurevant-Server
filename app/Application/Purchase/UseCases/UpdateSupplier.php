<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\SupplierDTO;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Supplier;

final class UpdateSupplier
{
    public function __construct(
        private SupplierRepositoryInterface $suppliers,
    ) {}

    public function __invoke(Supplier $supplier, array $attributes): SupplierDTO
    {
        return SupplierDTO::fromModel(
            $this->suppliers->update($supplier, $attributes)
        );
    }
}
