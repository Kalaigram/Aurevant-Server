<?php

declare(strict_types=1);

namespace App\Application\Company\DTOs;

use App\Models\Company;

final readonly class CompanyDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $legalName,
        public ?string $taxId,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $logoPath,
        public ?string $logoUrl,
        public string $slug,
        public string $status,
    ) {}

    public static function fromModel(Company $company): self
    {
        $logoPath = $company->logo_path;

        return new self(
            id: $company->id,
            name: $company->name,
            legalName: $company->legal_name,
            taxId: $company->tax_id,
            email: $company->email,
            phone: $company->phone,
            address: $company->address,
            logoPath: $logoPath,
            logoUrl: $logoPath !== null ? '/storage/'.ltrim($logoPath, '/') : null,
            slug: $company->slug,
            status: $company->status,
        );
    }
}
