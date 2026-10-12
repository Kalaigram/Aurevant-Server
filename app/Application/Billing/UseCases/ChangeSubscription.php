<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Billing\DTOs\SubscriptionDTO;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Validation\ValidationException;

final class ChangeSubscription
{
    public function __construct(
        private SubscriptionRepositoryInterface $billing,
    ) {}

    public function show(Company $company): SubscriptionDTO
    {
        $subscription = $this->billing->findForCompany($company) ?? abort(404);

        return SubscriptionDTO::fromModel($subscription);
    }

    public function change(Company $company, string $status, ?int $planId, ?string $endsAt): SubscriptionDTO
    {
        $subscription = $this->billing->findForCompany($company);
        if ($subscription === null) {
            $subscription = $this->billing->startTrial($company);
        }

        if ($planId !== null && Plan::query()->whereKey($planId)->doesntExist()) {
            throw ValidationException::withMessages([
                'plan_id' => 'Paket tidak ada.',
            ]);
        }

        $subscription->status = $status;
        $subscription->plan_id = $planId ?? $subscription->plan_id;
        $subscription->ends_at = $endsAt;

        return SubscriptionDTO::fromModel($this->billing->save($subscription));
    }
}
