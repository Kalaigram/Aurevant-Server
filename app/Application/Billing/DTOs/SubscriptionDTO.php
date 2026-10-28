<?php

declare(strict_types=1);

namespace App\Application\Billing\DTOs;

use App\Models\Subscription;

final readonly class SubscriptionDTO
{
    public function __construct(
        public ?int $planId,
        public ?string $planName,
        public string $status,
        public ?string $trialEndsAt,
        public ?string $endsAt,
        public bool $allowsWrites,
        public ?int $maxBranches = null,
        public ?int $maxEmployees = null,
        public int $branchesUsed = 0,
        public int $employeesUsed = 0,
    ) {}

    /** @param array{maxBranches: ?int, maxEmployees: ?int, branchesUsed: int, employeesUsed: int}|null $usage */
    public static function fromModel(Subscription $subscription, ?array $usage = null): self
    {
        return new self(
            planId: $subscription->plan_id,
            planName: $subscription->relationLoaded('plan') ? $subscription->plan?->name : null,
            status: $subscription->status,
            trialEndsAt: $subscription->trial_ends_at?->toDateString(),
            endsAt: $subscription->ends_at?->toDateString(),
            allowsWrites: $subscription->allowsWrites(),
            maxBranches: $usage['maxBranches'] ?? null,
            maxEmployees: $usage['maxEmployees'] ?? null,
            branchesUsed: $usage['branchesUsed'] ?? 0,
            employeesUsed: $usage['employeesUsed'] ?? 0,
        );
    }
}
