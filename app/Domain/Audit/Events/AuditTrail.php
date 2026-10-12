<?php

declare(strict_types=1);

namespace App\Domain\Audit\Events;

/** Satu peristiwa bisnis yang wajib tercatat. Disimpan oleh listener antrean. */
final readonly class AuditTrail
{
    public function __construct(
        public int $companyId,
        public ?int $branchId,
        public ?int $userId,
        public string $action,
        public string $entityType,
        public int|string|null $entityId,
        public ?array $before = null,
        public ?array $after = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
