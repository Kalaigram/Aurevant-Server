<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Billing\DTOs\PlanDTO;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Plan;

final class ManagePlans
{
    public function __construct(
        private SubscriptionRepositoryInterface $billing,
    ) {}

    public function create(array $attributes): PlanDTO
    {
        return PlanDTO::fromModel($this->billing->createPlan($attributes));
    }

    public function update(Plan $plan, array $attributes): PlanDTO
    {
        return PlanDTO::fromModel($this->billing->updatePlan($plan, $attributes));
    }

    public function delete(Plan $plan): void
    {
        $this->billing->deletePlan($plan);
    }
}
