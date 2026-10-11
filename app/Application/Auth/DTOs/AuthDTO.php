<?php

declare(strict_types=1);

namespace App\Application\Auth\DTOs;

final readonly class AuthDTO
{
    public function __construct(
        public UserDTO $user,
        public string $token,
    ) {}
}
