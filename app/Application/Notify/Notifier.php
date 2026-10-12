<?php

declare(strict_types=1);

namespace App\Application\Notify;

use App\Models\AppNotification;
use App\Models\Company;
use App\Models\NotificationPreference;
use App\Models\User;

/** In-app only (FCM/email menyusul). Hormati preferensi per user. */
final class Notifier
{
    /** @param list<string> $permissions kirim ke user yang punya salah satunya di company ini */
    public static function toPermissioned(
        Company $company,
        array $permissions,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?int $exceptUserId = null,
    ): int {
        setPermissionsTeamId($company->id);

        $sent = 0;
        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.company_id', $company->id))
            ->get();

        foreach ($users as $user) {
            if ($exceptUserId !== null && $user->id === $exceptUserId) {
                continue;
            }

            setPermissionsTeamId($company->id);
            $allowed = false;
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                continue;
            }

            if (NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('enabled', false)
                ->exists()
            ) {
                continue;
            }

            AppNotification::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link' => $link,
            ]);
            $sent++;
        }

        return $sent;
    }
}
