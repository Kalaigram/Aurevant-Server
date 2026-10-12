<?php

declare(strict_types=1);

namespace App\Application\Audit;

use App\Domain\Audit\Events\AuditTrail;
use App\Models\Branch;
use App\Models\Company;

/** Satu baris catat-aksi. Dipanggil dari use case, disimpan via event + antrean. */
final class Audit
{
    public static function record(
        Company $company,
        Branch|int|null $branch,
        string $action,
        string $entityType,
        int|string|null $entityId,
        ?array $before = null,
        ?array $after = null,
    ): void {
        $request = request();

        event(new AuditTrail(
            companyId: $company->id,
            branchId: $branch instanceof Branch ? $branch->id : $branch,
            userId: auth()->id(),
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            before: $before,
            after: $after,
            ip: $request?->ip(),
            userAgent: $request?->userAgent(),
        ));
    }
}
