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
    ) {}

    public static function fromModel(Subscription $subscription): self
    {
        return new self(
            planId: $subscription->plan_id,
            planName: $subscription->relationLoaded('plan') ? $subscription->plan?->name : null,
            status: $subscription->status,
            trialEndsAt: $subscription->trial_ends_at?->toDateString(),
            endsAt: $subscription->ends_at?->toDateString(),
            allowsWrites: $subscription->allowsWrites(),
        );
    }
}
