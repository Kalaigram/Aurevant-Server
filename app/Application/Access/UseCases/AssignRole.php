<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AssignRole
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    public function __invoke(Company $company, User $user, string $roleName): void
    {
        $role = $this->roles->findByNameForCompany($company, $roleName);
        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'Role tidak ada di perusahaan ini.',
            ]);
        }

        setPermissionsTeamId($company->id);
        $user->assignRole($role);
    }

    public function revoke(Company $company, User $user, string $roleName): void
    {
        $role = $this->roles->findByNameForCompany($company, $roleName);
        if ($role === null) {
            throw ValidationException::withMessages([
                'role' => 'Role tidak ada di perusahaan ini.',
            ]);
        }

        setPermissionsTeamId($company->id);
        $user->removeRole($role);
    }
}
