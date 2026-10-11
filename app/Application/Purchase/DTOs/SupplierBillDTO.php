<?php

declare(strict_types=1);

namespace App\Application\Purchase\DTOs;

use App\Models\SupplierBill;

final readonly class SupplierBillDTO
{
    public function __construct(
        public string $amount,
        public string $paidAmount,
        public ?string $dueAt,
    ) {}

    public static function fromModel(SupplierBill $bill): self
    {
        return new self(
            amount: (string) $bill->amount,
            paidAmount: (string) $bill->paid_amount,
            dueAt: $bill->due_at?->toDateString(),
        );
    }
}
