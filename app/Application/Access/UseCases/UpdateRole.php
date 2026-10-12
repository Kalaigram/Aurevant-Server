<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\RoleDTO;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

final class UpdateRole
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    /** @param list<string>|null $permissions */
    public function __invoke(Role $role, string $name, ?array $permissions): RoleDTO
    {
        $role->name = $name;
        $role->save();

        if ($permissions !== null) {
            $role = $this->roles->syncPermissions($role, $permissions);
        }

        return RoleDTO::fromModel($role->load('permissions'));
    }
}
