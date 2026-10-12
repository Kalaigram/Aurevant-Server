<?php

declare(strict_types=1);

namespace App\Application\Billing\DTOs;

use App\Models\Plan;

final readonly class PlanDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public int $maxBranches,
        public int $maxEmployees,
        public ?array $features,
        public string $price,
    ) {}

    public static function fromModel(Plan $plan): self
    {
        return new self(
            id: $plan->id,
            name: $plan->name,
            maxBranches: $plan->max_branches,
            maxEmployees: $plan->max_employees,
            features: $plan->features,
            price: (string) $plan->price,
        );
    }
}
