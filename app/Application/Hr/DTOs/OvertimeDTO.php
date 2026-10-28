<?php

declare(strict_types=1);

namespace App\Application\Hr\DTOs;

use App\Models\OvertimeRequest;

final readonly class OvertimeDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $userName,
        public string $date,
        public string $startTime,
        public string $endTime,
        public int $minutes,
        public string $reason,
        public string $status,
        public ?string $note,
        public ?string $decidedAt,
    ) {}

    public static function fromModel(OvertimeRequest $overtime): self
    {
        return new self(
            id: $overtime->id,
            userId: $overtime->user_id,
            userName: $overtime->relationLoaded('user') ? (string) $overtime->user?->name : '',
            date: $overtime->date->toDateString(),
            startTime: $overtime->start_time,
            endTime: $overtime->end_time,
            minutes: $overtime->minutes,
            reason: $overtime->reason,
            status: $overtime->status,
            note: $overtime->note,
            decidedAt: $overtime->decided_at?->toIso8601String(),
        );
    }
}
