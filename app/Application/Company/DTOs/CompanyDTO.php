<?php

declare(strict_types=1);

namespace App\Application\Company\DTOs;

use App\Models\Company;

final readonly class CompanyDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $status,
    ) {}

    public static function fromModel(Company $company): self
    {
        return new self(
            id: $company->id,
            name: $company->name,
            slug: $company->slug,
            status: $company->status,
        );
    }
}
