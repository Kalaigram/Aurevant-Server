<?php

declare(strict_types=1);

namespace App\Application\Ledger\DTOs;

use App\Models\AccountingPeriod;

final class AccountingPeriodDTO
{
    public function __construct(
        public int $id,
        public int $year,
        public int $month,
        public string $label,
        public string $status,
        public ?string $closedAt,
    ) {}

    public static function fromModel(AccountingPeriod $period): self
    {
        return new self(
            id: $period->id,
            year: $period->year,
            month: $period->month,
            label: $period->label(),
            status: $period->status,
            closedAt: $period->closed_at?->toDateTimeString(),
        );
    }
}
