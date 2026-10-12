<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Models\Company;
use App\Models\SubscriptionInvoice;
use Illuminate\Pagination\LengthAwarePaginator;

final class InvoiceEloquentRepository implements InvoiceRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return SubscriptionInvoice::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function create(Company $company, array $attributes): SubscriptionInvoice
    {
        return SubscriptionInvoice::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh()->load('plan');
    }

    public function save(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        $invoice->save();

        return $invoice->refresh()->load('plan');
    }
}
