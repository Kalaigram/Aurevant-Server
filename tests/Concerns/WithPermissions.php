<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Beri user satu role berisi SEMUA permission katalog di company ini. */
trait WithPermissions
{
    protected function owner(User $user, Company $company): User
    {
        setPermissionsTeamId($company->id);

        foreach (PermissionCatalog::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(
            ['name' => 'Pemilik', 'guard_name' => 'web', 'company_id' => $company->id],
        );
        $role->syncPermissions(RoleTemplates::permissionsFor('Pemilik', PermissionCatalog::all()));
        $user->assignRole($role);

        return $user->fresh();
    }
}
