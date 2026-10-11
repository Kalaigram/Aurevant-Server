<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Supplier;

final class DeleteSupplier
{
    public function __construct(
        private SupplierRepositoryInterface $suppliers,
    ) {}

    public function __invoke(Supplier $supplier): void
    {
        $this->suppliers->delete($supplier);
    }
}
