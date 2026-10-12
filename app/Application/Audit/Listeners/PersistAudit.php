<?php

declare(strict_types=1);

namespace App\Application\Audit\Listeners;

use App\Domain\Audit\Events\AuditTrail;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

final class PersistAudit implements ShouldQueue
{
    public function handle(AuditTrail $event): void
    {
        AuditLog::query()->create([
            'company_id' => $event->companyId,
            'branch_id' => $event->branchId,
            'user_id' => $event->userId,
            'action' => $event->action,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'before' => $event->before,
            'after' => $event->after,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
    }
}
