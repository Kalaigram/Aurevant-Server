<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\AuthDTO;
use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class LoginUser
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function __invoke(string $identifier, string $password): AuthDTO
    {
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $this->users->findByEmail($identifier)
            : $this->users->findByPhone($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Email/no. HP atau password salah.');
        }

        return new AuthDTO(
            user: UserDTO::fromModel($user),
            token: $user->createToken('api-token')->plainTextToken,
        );
    }
}
