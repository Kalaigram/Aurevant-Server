<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Domain\Access\Repositories\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

final class DeleteRole
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    public function __invoke(Role $role): void
    {
        $this->roles->delete($role);
    }
}
