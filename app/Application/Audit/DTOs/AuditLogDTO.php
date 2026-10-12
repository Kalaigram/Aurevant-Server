<?php

declare(strict_types=1);

namespace App\Application\Audit\DTOs;

use App\Models\AuditLog;

final readonly class AuditLogDTO
{
    public function __construct(
        public int $id,
        public ?int $branchId,
        public ?int $userId,
        public ?string $userName,
        public string $action,
        public string $entityType,
        public int|string|null $entityId,
        public ?array $before,
        public ?array $after,
        public ?string $ip,
        public string $createdAt,
    ) {}

    public static function fromModel(AuditLog $log): self
    {
        return new self(
            id: $log->id,
            branchId: $log->branch_id,
            userId: $log->user_id,
            userName: $log->relationLoaded('user') ? $log->user?->name : null,
            action: $log->action,
            entityType: $log->entity_type,
            entityId: $log->entity_id,
            before: $log->before,
            after: $log->after,
            ip: $log->ip,
            createdAt: $log->created_at->toISOString(),
        );
    }
}
