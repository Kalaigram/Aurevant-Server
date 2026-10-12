<?php

declare(strict_types=1);

namespace App\Application\Auth\DTOs;

use App\Models\User;

final readonly class UserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public bool $isSuperAdmin,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            isSuperAdmin: (bool) $user->is_super_admin,
        );
    }
}
