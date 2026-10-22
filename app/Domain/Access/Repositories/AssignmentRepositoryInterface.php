<?php

declare(strict_types=1);

namespace App\Domain\Access\Repositories;

use App\Models\Company;
use App\Models\EmployeeAssignment;
use Illuminate\Pagination\LengthAwarePaginator;

interface AssignmentRepositoryInterface
{
    /** @return LengthAwarePaginator<int, EmployeeAssignment> */
    public function paginateForCompany(
        Company $company,
        int $perPage,
        ?int $userId = null,
        bool $activeOnly = false,
        ?string $q = null,
        ?int $branchId = null,
        ?int $roleId = null,
    ): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?EmployeeAssignment;

    public function create(Company $company, array $attributes): EmployeeAssignment;

    public function save(EmployeeAssignment $assignment): EmployeeAssignment;
}
