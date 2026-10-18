<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use App\Application\Notify\Push\PushSender;

/** Dipakai saat FCM belum dikonfigurasi: tidak mengirim, dianggap sukses. */
final class NullPushSender implements PushSender
{
    public function send(string $deviceToken, string $title, ?string $body, array $data = []): bool
    {
        return true;
    }
}
