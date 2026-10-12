<?php

declare(strict_types=1);

namespace App\Application\Payroll\DTOs;

use App\Models\PayrollLine;

final readonly class PayrollLineDTO
{
    public function __construct(
        public int $userId,
        public ?int $componentId,
        public string $label,
        public string $amount,
        public bool $isManual,
    ) {}

    public static function fromModel(PayrollLine $line): self
    {
        return new self(
            userId: $line->user_id,
            componentId: $line->salary_component_id,
            label: $line->label,
            amount: (string) $line->amount,
            isManual: (bool) $line->is_manual,
        );
    }
}
