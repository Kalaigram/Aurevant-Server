<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;

interface SubscriptionRepositoryInterface
{
    public function findForCompany(Company $company): ?Subscription;

    public function startTrial(Company $company): Subscription;

    public function save(Subscription $subscription): Subscription;

    /** @return LengthAwarePaginator<int, Plan> */
    public function listPlans(int $perPage): LengthAwarePaginator;

    public function createPlan(array $attributes): Plan;

    public function updatePlan(Plan $plan, array $attributes): Plan;

    public function deletePlan(Plan $plan): void;
}
