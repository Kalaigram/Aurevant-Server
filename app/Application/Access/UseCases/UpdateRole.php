<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\RoleDTO;
use App\Application\Audit\Audit;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Models\Company;
use Spatie\Permission\Models\Role;

final class UpdateRole
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    /** @param list<string>|null $permissions */
    public function __invoke(Role $role, string $name, ?array $permissions): RoleDTO
    {
        $before = ['name' => $role->name];
        $role->name = $name;
        $role->save();

        if ($permissions !== null) {
            $role = $this->roles->syncPermissions($role, $permissions);
        }

        $dto = RoleDTO::fromModel($role->load('permissions'));
        Audit::record(
            Company::find($role->company_id), null, 'access.role.update', 'role', $role->id,
            $before,
            ['name' => $name, 'permissions' => $dto->permissions],
        );

        return $dto;
    }
}
