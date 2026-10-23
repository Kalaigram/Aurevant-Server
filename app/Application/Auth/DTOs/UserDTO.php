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
        public ?string $avatarPath,
        public ?string $avatarUrl,
        public ?string $employeeUuid,
        public ?string $ptkpStatus,
        public bool $isSuperAdmin,
    ) {}

    public static function fromModel(User $user): self
    {
        $avatarPath = $user->avatar_path;

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            avatarPath: $avatarPath,
            avatarUrl: $avatarPath !== null ? '/storage/'.ltrim($avatarPath, '/') : null,
            employeeUuid: $user->employee_uuid,
            ptkpStatus: $user->ptkp_status,
            isSuperAdmin: (bool) $user->is_super_admin,
        );
    }
}
