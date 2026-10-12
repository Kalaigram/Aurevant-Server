<?php

declare(strict_types=1);

namespace App\Application\Finance\DTOs;

use App\Models\Receivable;

final readonly class ReceivableDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public ?int $customerId,
        public string $amount,
        public string $paidAmount,
        public string $remaining,
        public ?string $dueAt,
        public bool $isPaid,
    ) {}

    public static function fromModel(Receivable $receivable): self
    {
        return new self(
            id: $receivable->id,
            branchId: $receivable->branch_id,
            customerId: $receivable->customer_id,
            amount: (string) $receivable->amount,
            paidAmount: (string) $receivable->paid_amount,
            remaining: (string) $receivable->remaining(),
            dueAt: $receivable->due_at?->toDateString(),
            isPaid: $receivable->remaining() <= 0,
        );
    }
}
