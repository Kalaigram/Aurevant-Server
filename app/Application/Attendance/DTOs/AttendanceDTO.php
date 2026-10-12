<?php

declare(strict_types=1);

namespace App\Application\Attendance\DTOs;

use App\Models\Attendance;

final readonly class AttendanceDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public string $date,
        public string $status,
        public ?string $clockInAt,
        public ?string $clockOutAt,
        public int $lateMinutes,
    ) {}

    public static function fromModel(Attendance $attendance): self
    {
        return new self(
            id: $attendance->id,
            branchId: $attendance->branch_id,
            date: $attendance->date->toDateString(),
            status: $attendance->status,
            clockInAt: $attendance->clock_in_at?->toISOString(),
            clockOutAt: $attendance->clock_out_at?->toISOString(),
            lateMinutes: $attendance->late_minutes,
        );
    }
}
