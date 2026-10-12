<?php

declare(strict_types=1);

namespace App\Application\Payroll\DTOs;

use App\Models\SalaryComponent;

final readonly class SalaryComponentDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $calcType,
        public bool $deductible,
    ) {}

    public static function fromModel(SalaryComponent $component): self
    {
        return new self(
            id: $component->id,
            name: $component->name,
            calcType: $component->calc_type,
            deductible: (bool) $component->deductible,
        );
    }
}
