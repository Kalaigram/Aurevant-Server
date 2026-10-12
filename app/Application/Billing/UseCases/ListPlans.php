<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Billing\DTOs\PlanDTO;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListPlans
{
    public function __construct(
        private SubscriptionRepositoryInterface $billing,
    ) {}

    /** @return LengthAwarePaginator<int, PlanDTO> */
    public function __invoke(int $perPage): LengthAwarePaginator
    {
        return $this->billing->listPlans($perPage)
            ->through(fn ($plan) => PlanDTO::fromModel($plan));
    }
}
