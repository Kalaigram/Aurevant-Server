<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreUserAvatar
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function __invoke(User $user, UploadedFile $file): UserDTO
    {
        $path = $file->store('user-avatars', 'public');

        if ($path === false) {
            abort(500, 'Foto gagal diunggah.');
        }

        $oldPath = $user->avatar_path;
        $updated = $this->users->update($user, ['avatar_path' => $path]);

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return UserDTO::fromModel($updated);
    }
}
