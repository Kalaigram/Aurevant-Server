<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

final class RoleEloquentRepository implements RoleRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Role
    {
        return Role::query()
            ->with('permissions')
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function findByNameForCompany(Company $company, string $name): ?Role
    {
        return Role::query()
            ->with('permissions')
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();
    }

    public function create(Company $company, string $name): Role
    {
        setPermissionsTeamId($company->id);

        return Role::create(['name' => $name, 'guard_name' => 'web'])->load('permissions');
    }

    public function syncPermissions(Role $role, array $permissions): Role
    {
        setPermissionsTeamId($role->company_id);

        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
