<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Services;

/** QR cabang dinamis: token HMAC per slot 45 detik. Murni, bisa di-unit-test. */
final class QrTokenService
{
    public const SLOT_SECONDS = 45;

    public function generate(string $secret, int $branchId, ?int $now = null): array
    {
        $slot = intdiv($now ?? time(), self::SLOT_SECONDS);

        return [
            'token' => $this->forSlot($secret, $branchId, $slot),
            'expires_in' => self::SLOT_SECONDS - (($now ?? time()) % self::SLOT_SECONDS),
        ];
    }

    public function validate(string $secret, int $branchId, string $token, ?int $now = null): bool
    {
        $slot = intdiv($now ?? time(), self::SLOT_SECONDS);

        foreach ([$slot - 1, $slot] as $candidate) {
            if (hash_equals($this->forSlot($secret, $branchId, $candidate), $token)) {
                return true;
            }
        }

        return false;
    }

    private function forSlot(string $secret, int $branchId, int $slot): string
    {
        return hash_hmac('sha256', $branchId.':'.$slot, $secret);
    }
}
