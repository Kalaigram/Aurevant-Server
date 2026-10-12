<?php

declare(strict_types=1);

namespace App\Application\Finance\DTOs;

use App\Models\SupplierBill;

final readonly class BillDTO
{
    public function __construct(
        public int $id,
        public string $amount,
        public string $paidAmount,
        public string $remaining,
        public ?string $dueAt,
        public bool $isPaid,
    ) {}

    public static function fromModel(SupplierBill $bill): self
    {
        return new self(
            id: $bill->id,
            amount: (string) $bill->amount,
            paidAmount: (string) $bill->paid_amount,
            remaining: (string) $bill->remaining(),
            dueAt: $bill->due_at?->toDateString(),
            isPaid: $bill->remaining() <= 0,
        );
    }
}
