<?php

declare(strict_types=1);

namespace App\Application\Company\UseCases;

use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\Company;

final class UpdateCompanyProfile
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {}

    /** @param array<string, mixed> $data */
    public function __invoke(Company $company, array $data): CompanyDTO
    {
        $attributes = [];

        foreach (['name', 'legal_name', 'tax_id', 'email', 'phone', 'address'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        return CompanyDTO::fromModel($this->companies->update($company, $attributes));
    }
}
