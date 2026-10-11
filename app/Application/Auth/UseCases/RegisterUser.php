<?php

declare(strict_types=1);

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\AuthDTO;
use App\Application\Auth\DTOs\UserDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

final class RegisterUser
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function __invoke(string $name, string $email, ?string $phone, string $password): AuthDTO
    {
        $user = $this->users->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
        ]);

        return new AuthDTO(
            user: UserDTO::fromModel($user),
            token: $user->createToken('api-token')->plainTextToken,
        );
    }
}
