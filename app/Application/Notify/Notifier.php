<?php

declare(strict_types=1);

namespace App\Application\Notify;

use App\Jobs\SendPushNotification;
use App\Models\AppNotification;
use App\Models\Company;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/** In-app + push FCM (email menyusul). Hormati preferensi per user. */
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
        int|array|null $exceptUserIds = null,
    ): int {
        setPermissionsTeamId($company->id);

        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.company_id', $company->id))
            ->get();

        return self::deliver($company, $users, $type, $title, $body, $link, $exceptUserIds, $permissions);
    }

    public static function toUser(
        Company $company,
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
    ): int {
        return self::deliver($company, collect([$user]), $type, $title, $body, $link, null, null);
    }

    /** Kirim ke semua pemegang role tertentu di company ini (mis. Pemilik). */
    public static function toRole(
        Company $company,
        string $roleName,
        string $type,
        string $title,
        ?string $body = null,
        ?string $link = null,
    ): int {
        setPermissionsTeamId($company->id);

        $users = User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.company_id', $company->id)->where('roles.name', $roleName))
            ->get();

        return self::deliver($company, $users, $type, $title, $body, $link, null, null);
    }

    /** @return Collection<int, User> */
    public static function usersWithRole(Company $company, string $roleName): Collection
    {
        setPermissionsTeamId($company->id);

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.company_id', $company->id)->where('roles.name', $roleName))
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  list<string>|null  $permissions  null = tanpa filter izin
     */
    private static function deliver(
        Company $company,
        Collection $users,
        string $type,
        string $title,
        ?string $body,
        ?string $link,
        int|array|null $exceptUserIds,
        ?array $permissions,
    ): int {
        $sent = 0;
        $except = $exceptUserIds === null
            ? []
            : (array) $exceptUserIds;
        $except = array_map('intval', $except);

        foreach ($users as $user) {
            if (in_array($user->id, $except, true)) {
                continue;
            }

            if ($permissions !== null) {
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

            SendPushNotification::dispatch($user->id, $title, $body, array_filter([
                'type' => $type,
                'link' => $link,
            ], fn ($value) => $value !== null && $value !== ''));

            $sent++;
        }

        return $sent;
    }
}
