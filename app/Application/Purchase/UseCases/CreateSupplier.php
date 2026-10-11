<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\SupplierDTO;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Company;

final class CreateSupplier
{
    public function __construct(
        private SupplierRepositoryInterface $suppliers,
    ) {}

    public function __invoke(Company $company, array $attributes): SupplierDTO
    {
        return SupplierDTO::fromModel(
            $this->suppliers->createForCompany($company, $attributes)
        );
    }
}
