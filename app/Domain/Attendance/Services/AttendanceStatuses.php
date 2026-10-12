<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Services;

/** Status absensi + flag potong gaji (dipakai penggajian nanti). */
final class AttendanceStatuses
{
    /** @return array<string, bool> status => memotong gaji */
    public static function all(): array
    {
        return [
            'hadir' => false,
            'terlambat' => false,
            'alpa' => true,
            'izin' => false,
            'sakit' => false,
            'cuti' => false,
        ];
    }
}
