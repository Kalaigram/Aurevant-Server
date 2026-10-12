<?php

declare(strict_types=1);

namespace App\Application\Notify\UseCases;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ManageNotifications
{
    /** @return LengthAwarePaginator<int, AppNotification> */
    public function list(User $user, int $perPage, ?int $companyId, bool $unreadOnly): LengthAwarePaginator
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function read(User $user, int $id): AppNotification
    {
        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->first() ?? abort(404);

        $notification->read_at ??= now();
        $notification->save();

        return $notification->refresh();
    }

    public function readAll(User $user, ?int $companyId): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->update(['read_at' => now()]);
    }
}
