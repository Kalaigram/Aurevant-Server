<?php

declare(strict_types=1);

namespace App\Application\Notify\Push;

interface PushSender
{
    /**
     * Kirim notifikasi push ke satu device token.
     *
     * @param  array<string, string|int|float|null>  $data
     * @return bool true = terkirim; false = token mati (harus dihapus)
     */
    public function send(string $deviceToken, string $title, ?string $body, array $data = []): bool;
}
