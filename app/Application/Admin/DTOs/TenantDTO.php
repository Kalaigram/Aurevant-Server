<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

use App\Models\Company;

final class TenantDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $status,
        public int $branchesCount,
        public int $usersCount,
        public ?string $planName,
        public ?string $subscriptionStatus,
        public ?string $endsAt,
        public string $createdAt,
    ) {}

    public static function fromModel(Company $company): self
    {
        $subscription = $company->relationLoaded('subscription') ? $company->subscription : null;

        return new self(
            id: $company->id,
            name: $company->name,
            slug: $company->slug,
            status: $company->status,
            branchesCount: (int) ($company->branches_count ?? $company->branches()->count()),
            usersCount: (int) ($company->users_count ?? 0),
            planName: $subscription?->plan?->name,
            subscriptionStatus: $subscription?->status,
            endsAt: $subscription?->ends_at?->toDateString() ?? $subscription?->trial_ends_at?->toDateString(),
            createdAt: $company->created_at->toDateTimeString(),
        );
    }
}
