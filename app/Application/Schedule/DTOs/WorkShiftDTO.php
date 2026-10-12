<?php

declare(strict_types=1);

namespace App\Application\Schedule\DTOs;

use App\Models\WorkShift;

final readonly class WorkShiftDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $startsAt,
        public string $endsAt,
        public int $lateToleranceMinutes,
        public bool $overnight,
    ) {}

    public static function fromModel(WorkShift $shift): self
    {
        return new self(
            id: $shift->id,
            name: $shift->name,
            startsAt: $shift->starts_at->format('H:i'),
            endsAt: $shift->ends_at->format('H:i'),
            lateToleranceMinutes: $shift->late_tolerance_minutes,
            overnight: $shift->isOvernight(),
        );
    }
}
