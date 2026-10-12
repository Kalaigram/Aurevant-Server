<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Application\Attendance\DTOs\AttendanceDTO;
use App\Application\Schedule\UseCases\ViewSchedule;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Attendance\Repositories\DeviceRepositoryInterface;
use App\Domain\Attendance\Services\GeoService;
use App\Domain\Attendance\Services\QrTokenService;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ClockIn
{
    public function __construct(
        private AttendanceRepositoryInterface $attendances,
        private BranchRepositoryInterface $branches,
        private DeviceRepositoryInterface $devices,
        private QrTokenService $qr,
        private GeoService $geo,
        private ViewSchedule $schedule,
        private ScheduleRepositoryInterface $shifts,
    ) {}

    public function __invoke(
        Company $company,
        User $user,
        int $branchId,
        string $qrToken,
        float $lat,
        float $lng,
        string $deviceId,
        ?string $selfiePath,
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
        if ($device === null) {
            $this->devices->bind($user, $deviceId);
        } elseif ($device->device_id !== $deviceId) {
            throw ValidationException::withMessages([
                'device_id' => 'Perangkat tidak dikenal, minta HR reset.',
            ]);
        }

        if ($branch->latitude !== null && $branch->longitude !== null
            && ! $this->geo->withinRadius((float) $branch->latitude, (float) $branch->longitude, $lat, $lng, $branch->radius_m)
        ) {
            throw ValidationException::withMessages([
                'location' => 'Di luar jangkauan cabang.',
            ]);
        }

        $now = now();
        $date = $now->copy()->setTimezone($branch->timezone)->toDateString();

        $existing = $this->attendances->findForDay($user, $branch, $date);
        if ($existing !== null && $existing->clock_in_at !== null) {
            throw ValidationException::withMessages([
                'attendance' => 'Sudah absen masuk hari ini.',
            ]);
        }

        $days = ($this->schedule)($company, $user, $date, $date);
        $today = $days[0];
        if ($today->isOff() || $today->startsAt === null) {
            throw ValidationException::withMessages([
                'attendance' => 'Tidak ada jadwal kerja hari ini.',
            ]);
        }

        $late = $this->lateMinutes($date, $today->startsAt, $now, $branch->timezone);
        $shift = $this->shifts->findShift($company, $today->shiftId);
        $tolerance = $shift === null ? 0 : $shift->late_tolerance_minutes;

        $attendance = $this->attendances->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'date' => $date,
            'status' => $late > $tolerance ? 'terlambat' : 'hadir',
            'clock_in_at' => $now,
            'late_minutes' => $late,
            'clock_in_lat' => $lat,
            'clock_in_lng' => $lng,
            'clock_in_selfie' => $selfiePath,
            'clock_in_device' => $deviceId,
        ]);

        return AttendanceDTO::fromModel($attendance);
    }

    private function lateMinutes(string $date, string $startsAt, \DateTimeInterface $now, string $timezone): int
    {
        $start = new \DateTimeImmutable("{$date} {$startsAt}", new \DateTimeZone($timezone));
        $diff = $now->getTimestamp() - $start->getTimestamp();

        return $diff > 0 ? intdiv($diff, 60) : 0;
    }
}
