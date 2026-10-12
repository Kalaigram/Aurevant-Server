<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repositories;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    /** @return LengthAwarePaginator<int, AuditLog> */
    public function paginateForCompany(
        Company $company,
        int $perPage,
        ?string $action = null,
        ?string $entityType = null,
        ?int $userId = null,
    ): LengthAwarePaginator;
}
