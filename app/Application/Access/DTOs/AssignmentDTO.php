<?php

declare(strict_types=1);

namespace App\Application\Access\DTOs;

use App\Models\EmployeeAssignment;

final readonly class AssignmentDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public ?string $userName,
        public ?string $userEmail,
        public ?int $branchId,
        public ?string $branchCode,
        public ?string $branchName,
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
            userName: $assignment->relationLoaded('user') ? $assignment->user?->name : null,
            userEmail: $assignment->relationLoaded('user') ? $assignment->user?->email : null,
            branchId: $assignment->branch_id,
            branchCode: $assignment->relationLoaded('branch') ? $assignment->branch?->code : null,
            branchName: $assignment->relationLoaded('branch') ? $assignment->branch?->name : null,
            roleId: $assignment->role_id,
            roleName: $assignment->relationLoaded('role') ? $assignment->role?->name : null,
            startedAt: $assignment->started_at->toDateString(),
            endedAt: $assignment->ended_at?->toDateString(),
        );
    }
}
