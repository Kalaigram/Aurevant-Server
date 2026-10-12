<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Attendance\Repositories\DeviceRepositoryInterface;
use App\Models\User;
use App\Models\UserDevice;

final class DeviceEloquentRepository implements DeviceRepositoryInterface
{
    public function findForUser(User $user): ?UserDevice
    {
        return UserDevice::query()->where('user_id', $user->id)->first();
    }

    public function bind(User $user, string $deviceId): UserDevice
    {
        return UserDevice::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['device_id' => $deviceId]
        );
    }

    public function reset(User $user): void
    {
        UserDevice::query()->where('user_id', $user->id)->delete();
    }
}
