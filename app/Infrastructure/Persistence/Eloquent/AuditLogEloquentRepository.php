<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class AuditLogEloquentRepository implements AuditLogRepositoryInterface
{
    public function paginateForCompany(
        Company $company,
        int $perPage,
        ?string $action = null,
        ?string $entityType = null,
        ?int $userId = null,
    ): LengthAwarePaginator {
        return AuditLog::query()
            ->with(['user', 'branch'])
            ->where('company_id', $company->id)
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->when($entityType !== null, fn ($query) => $query->where('entity_type', $entityType))
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
