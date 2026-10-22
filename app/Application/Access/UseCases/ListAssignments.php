<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAssignments
{
    public function __construct(
        private AssignmentRepositoryInterface $assignments,
    ) {}

    /** @return LengthAwarePaginator<int, AssignmentDTO> */
    public function __invoke(
        Company $company,
        int $perPage,
        ?int $userId,
        bool $activeOnly,
        ?string $q = null,
        ?int $branchId = null,
        ?int $roleId = null,
    ): LengthAwarePaginator {
        return $this->assignments->paginateForCompany(
            $company,
            $perPage,
            $userId,
            $activeOnly,
            $q,
            $branchId,
            $roleId,
        )->through(fn ($assignment) => AssignmentDTO::fromModel($assignment));
    }
}
