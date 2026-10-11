<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;

final class UserEloquentRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return User::query()->where('phone', $phone)->first();
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
