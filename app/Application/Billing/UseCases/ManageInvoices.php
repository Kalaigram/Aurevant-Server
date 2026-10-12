<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Audit\Audit;
use App\Application\Billing\DTOs\InvoiceDTO;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
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

        Audit::record($company, null, 'langganan.tagih', 'subscription_invoice', $invoice->id);

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

            Audit::record($company, null, 'langganan.bayar', 'subscription_invoice', $invoice->id);

            return InvoiceDTO::fromModel($invoice->refresh());
        });
    }

    /**
     * Terbitkan tagihan untuk langganan aktif yang habis <= 7 hari.
     * Lewati yang sudah punya tagihan menunggu atau tanpa paket.
     */
    public function generateDue(): int
    {
        $limit = now()->addDays(7)->toDateString();
        $count = 0;

        $subscriptions = Subscription::query()
            ->with('plan')
            ->whereIn('status', Subscription::ACTIVE)
            ->whereNotNull('plan_id')
            ->where(function ($query) use ($limit): void {
                $query->whereDate('ends_at', '<=', $limit)
                    ->orWhereNull('ends_at');
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            $pending = SubscriptionInvoice::query()
                ->where('company_id', $subscription->company_id)
                ->where('status', 'menunggu')
                ->exists();

            if ($pending || $subscription->plan === null) {
                continue;
            }

            $this->invoices->create($subscription->company, [
                'plan_id' => $subscription->plan_id,
                'amount' => (float) $subscription->plan->price,
                'due_at' => $subscription->ends_at?->toDateString() ?? now()->addDays(7)->toDateString(),
                'note' => 'Otomatis: masa aktif habis.',
            ]);
            $count++;
        }

        return $count;
    }
}
