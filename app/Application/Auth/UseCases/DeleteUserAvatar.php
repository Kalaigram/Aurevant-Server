<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class DeleteUserAvatar
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function __invoke(User $user): UserDTO
    {
        $oldPath = $user->avatar_path;
        $updated = $this->users->update($user, ['avatar_path' => null]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return UserDTO::fromModel($updated);
    }
}
