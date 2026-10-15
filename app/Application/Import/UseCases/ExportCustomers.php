<?php

declare(strict_types=1);

namespace App\Application\Import\UseCases;

use App\Models\Company;

final class ExportCustomers
{
    /** @return list<array<string, string>> */
    public function __invoke(Company $company): array
    {
        $rows = [];
        foreach ($company->customers()->orderBy('name')->cursor() as $customer) {
            $rows[] = [
                'name' => $customer->name,
                'phone' => (string) ($customer->phone ?? ''),
                'address' => (string) ($customer->address ?? ''),
            ];
        }

        return $rows;
    }
}
