<?php

declare(strict_types=1);

namespace App\Domain\Access\Repositories;

use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Role> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Role;

    public function findByNameForCompany(Company $company, string $name): ?Role;

    public function create(Company $company, string $name): Role;

    public function syncPermissions(Role $role, array $permissions): Role;

    public function delete(Role $role): void;
}
