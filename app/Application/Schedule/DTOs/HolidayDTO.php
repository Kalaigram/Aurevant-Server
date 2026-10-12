<?php

declare(strict_types=1);

namespace App\Application\Schedule\DTOs;

use App\Models\Holiday;

final readonly class HolidayDTO
{
    public function __construct(
        public int $id,
        public string $date,
        public string $name,
    ) {}

    public static function fromModel(Holiday $holiday): self
    {
        return new self(
            id: $holiday->id,
            date: $holiday->date->toDateString(),
            name: $holiday->name,
        );
    }
}
