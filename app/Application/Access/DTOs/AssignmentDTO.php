<?php

declare(strict_types=1);

namespace App\Application\Access\DTOs;

use App\Models\EmployeeAssignment;

final readonly class AssignmentDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public ?int $branchId,
        public ?int $roleId,
        public ?string $roleName,
        public string $startedAt,
        public ?string $endedAt,
    ) {}

    public static function fromModel(EmployeeAssignment $assignment): self
    {
        return new self(
            id: $assignment->id,
            userId: $assignment->user_id,
            branchId: $assignment->branch_id,
            roleId: $assignment->role_id,
            roleName: $assignment->relationLoaded('role') ? $assignment->role?->name : null,
            startedAt: $assignment->started_at->toDateString(),
            endedAt: $assignment->ended_at?->toDateString(),
        );
    }
}
