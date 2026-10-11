<?php

declare(strict_types=1);

namespace App\Application\Branch\DTOs;

use App\Models\Branch;

final readonly class BranchDTO
{
    public function __construct(
        public int $id,
        public int $companyId,
        public string $code,
        public string $name,
        public string $type,
        public string $timezone,
        public ?string $address,
        public ?string $phone,
        public string $status,
    ) {}

    public static function fromModel(Branch $branch): self
    {
        return new self(
            id: $branch->id,
            companyId: $branch->company_id,
            code: $branch->code,
            name: $branch->name,
            type: $branch->type,
            timezone: $branch->timezone,
            address: $branch->address,
            phone: $branch->phone,
            status: $branch->status,
        );
    }
}
