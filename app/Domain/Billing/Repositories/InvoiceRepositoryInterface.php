<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Models\Company;
use App\Models\SubscriptionInvoice;
use Illuminate\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SubscriptionInvoice> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?SubscriptionInvoice;

    public function create(Company $company, array $attributes): SubscriptionInvoice;

    public function save(SubscriptionInvoice $invoice): SubscriptionInvoice;
}
