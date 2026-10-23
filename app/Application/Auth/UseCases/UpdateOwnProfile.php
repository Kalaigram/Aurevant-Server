<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;

final class UpdateOwnProfile
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    /** @param array{name: string, phone?: string|null} $data */
    public function __invoke(User $user, array $data): UserDTO
    {
        $attributes = ['name' => $data['name']];

        if (array_key_exists('phone', $data)) {
            $attributes['phone'] = $data['phone'] !== '' ? $data['phone'] : null;
        }

        return UserDTO::fromModel($this->users->update($user, $attributes));
    }
}
