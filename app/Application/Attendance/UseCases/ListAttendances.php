<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Application\Attendance\DTOs\AttendanceDTO;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Attendance\Repositories\DeviceRepositoryInterface;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAttendances
{
    public function __construct(
        private AttendanceRepositoryInterface $attendances,
        private DeviceRepositoryInterface $devices,
    ) {}

    /** @return LengthAwarePaginator<int, AttendanceDTO> */
    public function mine(User $user, int $perPage, string $from, string $to): LengthAwarePaginator
    {
        return $this->attendances->paginateForUser($user, $perPage, $from, $to)
            ->through(fn ($attendance) => AttendanceDTO::fromModel($attendance));
    }

    /** @return LengthAwarePaginator<int, AttendanceDTO> */
    public function forBranch(Branch $branch, int $perPage, string $date): LengthAwarePaginator
    {
        return $this->attendances->paginateForBranch($branch, $perPage, $date)
            ->through(fn ($attendance) => AttendanceDTO::fromModel($attendance));
    }

    public function resetDevice(User $user): void
    {
        $this->devices->reset($user);
    }
}
