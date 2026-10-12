<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\CashShift;

final readonly class ShiftDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public string $status,
        public string $openingCash,
        public ?string $closingCashCounted,
        public ?string $closingCashSystem,
        public ?string $difference,
    ) {}

    public static function fromModel(CashShift $shift): self
    {
        $str = fn ($value) => $value === null ? null : (string) $value;

        return new self(
            id: $shift->id,
            branchId: $shift->branch_id,
            status: $shift->status,
            openingCash: (string) $shift->opening_cash,
            closingCashCounted: $str($shift->closing_cash_counted),
            closingCashSystem: $str($shift->closing_cash_system),
            difference: $str($shift->difference),
        );
    }
}
