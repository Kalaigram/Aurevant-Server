<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;

final class SubscriptionEloquentRepository implements SubscriptionRepositoryInterface
{
    public function findForCompany(Company $company): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->first();
    }

    public function startTrial(Company $company): Subscription
    {
        $plan = Plan::query()->firstOrCreate(
            ['name' => 'Dasar'],
            ['max_branches' => 3, 'max_employees' => 20, 'features' => ['kasir', 'stok', 'po', 'penggajian'], 'price' => 99000],
        );

        return Subscription::query()->firstOrCreate(
            ['company_id' => $company->id],
            ['plan_id' => $plan->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays(14)->toDateString()],
        );
    }

    public function save(Subscription $subscription): Subscription
    {
        $subscription->save();

        return $subscription->refresh()->load('plan');
    }

    public function listPlans(int $perPage): LengthAwarePaginator
    {
        return Plan::query()->orderBy('price')->paginate($perPage);
    }

    public function createPlan(array $attributes): Plan
    {
        return Plan::query()->create($attributes)->refresh();
    }

    public function updatePlan(Plan $plan, array $attributes): Plan
    {
        $plan->update($attributes);

        return $plan->refresh();
    }

    public function deletePlan(Plan $plan): void
    {
        $plan->delete();
    }
}
