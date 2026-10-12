<?php

declare(strict_types=1);

namespace App\Application\Leave\DTOs;

use App\Models\LeaveRequest;

final readonly class LeaveDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $type,
        public string $fromDate,
        public string $toDate,
        public string $reason,
        public string $status,
    ) {}

    public static function fromModel(LeaveRequest $leave): self
    {
        return new self(
            id: $leave->id,
            userId: $leave->user_id,
            type: $leave->type,
            fromDate: $leave->from_date->toDateString(),
            toDate: $leave->to_date->toDateString(),
            reason: $leave->reason,
            status: $leave->status,
        );
    }
}
