<?php

declare(strict_types=1);

namespace App\Application\Access\DTOs;

use Spatie\Permission\Models\Role;

final readonly class RoleDTO
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var list<string> */
        public array $permissions,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            permissions: $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->all()
                : [],
        );
    }
}
