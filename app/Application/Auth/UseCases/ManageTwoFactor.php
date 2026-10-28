<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageTwoFactor
{
    /** @return array{enabled: bool, pending: bool} */
    public function status(User $user): array
    {
        return [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->two_factor_secret !== null && ! $user->hasTwoFactorEnabled(),
        ];
    }

    /** @return array{secret: string, otpauthUri: string, recoveryCodes: list<string>} */
    public function setup(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => '2FA sudah aktif. Nonaktifkan terlebih dahulu.',
            ]);
        }

        $secret = Totp::generateSecret();
        $codes = collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(10)))
            ->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes),
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauthUri' => Totp::provisioningUri($secret, $user->email),
            'recoveryCodes' => $codes,
        ];
    }

    public function enable(User $user, string $code): void
    {
        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'two_factor' => 'Mulai pengaturan 2FA terlebih dahulu.',
            ]);
        }

        if (! Totp::verify($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => 'Kode 2FA salah.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password salah.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
