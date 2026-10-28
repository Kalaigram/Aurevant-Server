<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\AuthDTO;
use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginUser
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function __invoke(
        string $identifier,
        string $password,
        ?string $code = null,
        ?string $deviceName = null,
    ): AuthDTO {
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $this->users->findByEmail($identifier)
            : $this->users->findByPhone($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Email/no. HP atau password salah.');
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->verifyChallenge($user, $code);
        }

        $tokenName = $deviceName !== null && trim($deviceName) !== ''
            ? mb_substr(trim($deviceName), 0, 100)
            : 'api-token';

        return new AuthDTO(
            user: UserDTO::fromModel($user),
            token: $user->createToken($tokenName)->plainTextToken,
        );
    }

    private function verifyChallenge(User $user, ?string $code): void
    {
        if ($code === null || trim($code) === '') {
            throw ValidationException::withMessages([
                'two_factor_code' => 'Kode 2FA wajib diisi.',
            ]);
        }

        if ($user->two_factor_secret !== null && Totp::verify($user->two_factor_secret, $code)) {
            return;
        }

        $recoveryCodes = (array) ($user->two_factor_recovery_codes ?? []);

        foreach ($recoveryCodes as $index => $hashed) {
            if (Hash::check(strtoupper(trim($code)), (string) $hashed)) {
                unset($recoveryCodes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

                return;
            }
        }

        throw ValidationException::withMessages([
            'two_factor_code' => 'Kode 2FA salah.',
        ]);
    }
}
