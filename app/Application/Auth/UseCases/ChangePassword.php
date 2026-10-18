<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePassword
{
    /** @throws ValidationException saat password saat ini salah */
    public function __invoke(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        // cabut sesi lain, sesi yang sedang dipakai tetap hidup
        $tokenId = $user->currentAccessToken()?->id;
        $query = $user->tokens();
        if ($tokenId !== null) {
            $query->whereKeyNot($tokenId);
        }
        $query->delete();
    }
}
