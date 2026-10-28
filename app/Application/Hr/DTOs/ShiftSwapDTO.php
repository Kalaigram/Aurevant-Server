<?php

declare(strict_types=1);

namespace App\Application\Hr\DTOs;

use App\Models\ShiftSwap;

final readonly class ShiftSwapDTO
{
    public function __construct(
        public int $id,
        public int $requesterId,
        public string $requesterName,
        public int $targetUserId,
        public string $targetName,
        public string $date,
        public ?int $requesterShiftId,
        public ?string $requesterShiftName,
        public ?int $targetShiftId,
        public ?string $targetShiftName,
        public ?string $note,
        public string $status,
    ) {}

    public static function fromModel(ShiftSwap $swap): self
    {
        return new self(
            id: $swap->id,
            requesterId: $swap->requester_id,
            requesterName: $swap->relationLoaded('requester') ? (string) $swap->requester?->name : '',
            targetUserId: $swap->target_user_id,
            targetName: $swap->relationLoaded('target') ? (string) $swap->target?->name : '',
            date: $swap->date->toDateString(),
            requesterShiftId: $swap->requester_shift_id,
            requesterShiftName: $swap->relationLoaded('requesterShift') ? $swap->requesterShift?->name : null,
            targetShiftId: $swap->target_shift_id,
            targetShiftName: $swap->relationLoaded('targetShift') ? $swap->targetShift?->name : null,
            note: $swap->note,
            status: $swap->status,
        );
    }
}
