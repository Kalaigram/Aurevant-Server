<?php

declare(strict_types=1);

namespace App\Application\Payroll\DTOs;

use App\Models\PayrollPeriod;

final readonly class PayrollPeriodDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $from,
        public string $to,
        public int $divisor,
        public string $status,
    ) {}

    public static function fromModel(PayrollPeriod $period): self
    {
        return new self(
            id: $period->id,
            name: $period->name,
            from: $period->date_from->toDateString(),
            to: $period->date_to->toDateString(),
            divisor: $period->divisor,
            status: $period->status,
        );
    }
}
