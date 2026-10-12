<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Billing\DTOs\InvoiceDTO;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Company;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageInvoices
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private SubscriptionRepositoryInterface $billing,
    ) {}

    /** @return LengthAwarePaginator<int, InvoiceDTO> */
    public function list(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->invoices->paginateForCompany($company, $perPage)
            ->through(fn ($invoice) => InvoiceDTO::fromModel($invoice));
    }

    public function issue(Company $company, array $attributes): InvoiceDTO
    {
        if (isset($attributes['plan_id'])
            && Plan::query()->whereKey((int) $attributes['plan_id'])->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'plan_id' => 'Paket tidak ada.',
            ]);
        }

        $invoice = $this->invoices->create($company, [
            ...$attributes,
            'status' => 'menunggu',
        ]);

        return InvoiceDTO::fromModel($invoice);
    }

    public function pay(Company $company, SubscriptionInvoice $invoice): InvoiceDTO
    {
        if ($invoice->status === 'lunas') {
            throw ValidationException::withMessages([
                'invoice' => 'Sudah lunas.',
            ]);
        }

        return DB::transaction(function () use ($company, $invoice) {
            $invoice->status = 'lunas';
            $invoice->paid_at = now()->toDateString();
            $this->invoices->save($invoice);

            // Perpanjang 30 hari dari berakhir saat ini (atau hari ini bila sudah lewat).
            $subscription = $this->billing->findForCompany($company) ?? $this->billing->startTrial($company);
            $start = $subscription->ends_at !== null && $subscription->ends_at->greaterThan(now())
                ? $subscription->ends_at
                : now();
            $subscription->status = 'aktif';
            $subscription->plan_id = $invoice->plan_id ?? $subscription->plan_id;
            $subscription->ends_at = $start->copy()->addDays(30)->toDateString();
            $this->billing->save($subscription);

            return InvoiceDTO::fromModel($invoice->refresh());
        });
    }
}
