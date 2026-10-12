<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Models\User;
use App\Models\UserDevice;

interface DeviceRepositoryInterface
{
    public function findForUser(User $user): ?UserDevice;

    public function bind(User $user, string $deviceId): UserDevice;

    public function reset(User $user): void;
}
