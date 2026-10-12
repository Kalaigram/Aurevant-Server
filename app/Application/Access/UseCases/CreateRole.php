<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\RoleDTO;
use App\Application\Audit\Audit;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Models\Company;

final class CreateRole
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    /** @param list<string> $permissions */
    public function __invoke(Company $company, string $name, array $permissions, ?string $template = null): RoleDTO
    {
        if ($template !== null) {
            $permissions = RoleTemplates::permissionsFor($template, PermissionCatalog::all());
        }

        $role = $this->roles->create($company, $name);
        $dto = RoleDTO::fromModel($this->roles->syncPermissions($role, $permissions));

        Audit::record(
            $company, null, 'access.role.create', 'role', $dto->id,
            null,
            ['name' => $name, 'permissions' => $dto->permissions],
        );

        return $dto;
    }
}
