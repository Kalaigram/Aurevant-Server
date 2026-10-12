<?php

declare(strict_types=1);

namespace App\Application\Approval\DTOs;

use App\Models\Approval;

final class ApprovalDTO
{
    public function __construct(
        public int $id,
        public ?int $branchId,
        public ?string $entityType,
        public ?int $entityId,
        public string $action,
        public string $actionLabel,
        public string $status,
        public ?array $payload,
        public int $requestedBy,
        public ?int $decidedBy,
        public ?string $decidedAt,
        public ?string $note,
    ) {}

    /** @param array{label: string}|null $meta */
    public static function fromModel(Approval $approval, ?array $meta = null): self
    {
        return new self(
            id: $approval->id,
            branchId: $approval->branch_id,
            entityType: $approval->entity_type,
            entityId: $approval->entity_id === null ? null : (int) $approval->entity_id,
            action: $approval->action,
            actionLabel: $meta['label'] ?? $approval->action,
            status: $approval->status,
            payload: $approval->payload,
            requestedBy: $approval->requested_by,
            decidedBy: $approval->decided_by,
            decidedAt: $approval->decided_at?->toDateTimeString(),
            note: $approval->note,
        );
    }
}
