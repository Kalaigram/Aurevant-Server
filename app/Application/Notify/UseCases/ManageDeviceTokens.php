<?php

declare(strict_types=1);

namespace App\Application\Notify\UseCases;

use App\Models\User;
use App\Models\UserDeviceToken;

final class ManageDeviceTokens
{
    public function register(User $user, string $token, ?string $platform): void
    {
        UserDeviceToken::query()->updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform]
        );
    }

    public function remove(User $user, string $token): void
    {
        UserDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete();
    }
}
