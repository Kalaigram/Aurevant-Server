<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Application\Attendance\DTOs\AttendanceDTO;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Attendance\Repositories\DeviceRepositoryInterface;
use App\Domain\Attendance\Services\GeoService;
use App\Domain\Attendance\Services\QrTokenService;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ClockOut
{
    public function __construct(
        private AttendanceRepositoryInterface $attendances,
        private BranchRepositoryInterface $branches,
        private DeviceRepositoryInterface $devices,
        private QrTokenService $qr,
        private GeoService $geo,
    ) {}

    public function __invoke(
        Company $company,
        User $user,
        int $branchId,
        string $qrToken,
        float $lat,
        float $lng,
        string $deviceId,
    ): AttendanceDTO {
        $branch = $this->branches->findForCompany($company, $branchId) ?? abort(404);
        Access::requireBranch($user, $company, $branch->id);

        if ($branch->qr_secret === null) {
            $branch->qr_secret = Str::random(32);
            $branch->save();
        }

        if (! $this->qr->validate($branch->qr_secret, $branch->id, $qrToken)) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR tidak valid atau kedaluwarsa.',
            ]);
        }

        $device = $this->devices->findForUser($user);
        if ($device === null || $device->device_id !== $deviceId) {
            throw ValidationException::withMessages([
                'device_id' => 'Perangkat tidak dikenal.',
            ]);
        }

        if ($branch->latitude !== null && $branch->longitude !== null
            && ! $this->geo->withinRadius((float) $branch->latitude, (float) $branch->longitude, $lat, $lng, $branch->radius_m)
        ) {
            throw ValidationException::withMessages([
                'location' => 'Di luar jangkauan cabang.',
            ]);
        }

        $date = now()->copy()->setTimezone($branch->timezone)->toDateString();
        $attendance = $this->attendances->findForDay($user, $branch, $date);

        if ($attendance === null || $attendance->clock_in_at === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Belum absen masuk hari ini.',
            ]);
        }

        if ($attendance->clock_out_at !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'Sudah absen pulang hari ini.',
            ]);
        }

        $attendance->clock_out_at = now();

        return AttendanceDTO::fromModel($this->attendances->save($attendance));
    }
}
