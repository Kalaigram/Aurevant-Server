<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

final class ResetPassword
{
    /** @throws ValidationException saat token tidak valid atau kedaluwarsa */
    public function __invoke(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $password,
                'password_confirmation' => $password,
            ],
            function (User $user, string $plainPassword): void {
                $user->forceFill([
                    'password' => Hash::make($plainPassword),
                ])->save();

                // semua sesi lama dicabut; user login ulang dengan password baru
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Tautan atur ulang tidak valid atau sudah kedaluwarsa.'],
            ]);
        }
    }
}
