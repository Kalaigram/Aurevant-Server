<?php

declare(strict_types=1);

namespace App\Application\Billing\DTOs;

use App\Models\SubscriptionInvoice;

final class InvoiceDTO
{
    public function __construct(
        public int $id,
        public ?array $plan,
        public string $amount,
        public string $status,
        public ?string $dueAt,
        public ?string $paidAt,
        public ?string $note,
    ) {}

    public static function fromModel(SubscriptionInvoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            plan: $invoice->plan === null ? null : [
                'id' => $invoice->plan->id,
                'name' => $invoice->plan->name,
            ],
            amount: (string) $invoice->amount,
            status: $invoice->status,
            dueAt: $invoice->due_at?->toDateString(),
            paidAt: $invoice->paid_at?->toDateString(),
            note: $invoice->note,
        );
    }
}
