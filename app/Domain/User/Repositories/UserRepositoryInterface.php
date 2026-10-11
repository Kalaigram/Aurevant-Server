<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByPhone(string $phone): ?User;

    public function create(array $attributes): User;
}
