<?php

declare(strict_types=1);

namespace App\Application\Audit\UseCases;

use App\Application\Audit\DTOs\AuditLogDTO;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAuditLogs
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
    ) {}

    /** @return LengthAwarePaginator<int, AuditLogDTO> */
    public function __invoke(
        Company $company,
        int $perPage,
        ?string $action,
        ?string $entityType,
        ?int $userId,
    ): LengthAwarePaginator {
        return $this->logs->paginateForCompany($company, $perPage, $action, $entityType, $userId)
            ->through(fn ($log) => AuditLogDTO::fromModel($log));
    }
}
