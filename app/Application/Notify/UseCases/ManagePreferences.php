<?php

declare(strict_types=1);

namespace App\Application\Notify\UseCases;

use App\Application\Notify\NotificationTypes;
use App\Models\NotificationPreference;
use App\Models\User;

final class ManagePreferences
{
    /** @return list<array{type: string, enabled: bool}> */
    public function list(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->pluck('enabled', 'type')
            ->all();

        return array_map(
            fn ($type) => ['type' => $type, 'enabled' => $stored[$type] ?? true],
            NotificationTypes::all()
        );
    }

    /** @return array{type: string, enabled: bool} */
    public function set(User $user, string $type, bool $enabled): array
    {
        if (! in_array($type, NotificationTypes::all(), true)) {
            abort(404);
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            ['enabled' => $enabled]
        );

        return ['type' => $type, 'enabled' => $enabled];
    }
}
