<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Beri user satu role berisi SEMUA permission katalog + trial aktif di company ini. */
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

        $hasActiveAssignment = EmployeeAssignment::query()
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->exists();

        if (! $hasActiveAssignment) {
            EmployeeAssignment::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'branch_id' => null,
                'role_id' => $role->id,
                'started_at' => now()->toDateString(),
            ]);
        }

        app(SubscriptionRepositoryInterface::class)->startTrial($company);

        return $user->fresh();
    }

    protected function member(User $user, Company $company, ?int $branchId = null): EmployeeAssignment
    {
        return EmployeeAssignment::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'branch_id' => $branchId,
            'started_at' => now()->toDateString(),
        ]);
    }
}
