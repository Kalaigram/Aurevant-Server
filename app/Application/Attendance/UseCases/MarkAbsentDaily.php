<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Application\Schedule\UseCases\ViewSchedule;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\User;

final class MarkAbsentDaily
{
    public function __construct(
        private AttendanceRepositoryInterface $attendances,
        private LeaveRepositoryInterface $leaves,
        private AssignmentRepositoryInterface $assignments,
        private ScheduleRepositoryInterface $schedules,
        private ViewSchedule $schedule,
    ) {}

    /** @return int jumlah ditandai alpa */
    public function __invoke(Company $company, string $date): int
    {
        $userIds = $this->schedules->scheduledUserIds($company, $date, $date);
        $marked = 0;

        foreach ($userIds as $userId) {
            $user = User::query()->whereKey($userId)->first();
            if ($user === null) {
                continue;
            }

            $days = ($this->schedule)($company, $user, $date, $date);
            if ($days[0]->isOff) {
                continue;
            }

            if ($this->attendances->existsForDay($user, $date)) {
                continue;
            }

            if ($this->leaves->approvedCovering($user, $date) !== null) {
                continue;
            }

            $assignment = $this->assignments->paginateForCompany($company, 1, $user->id, true)->first();

            $this->attendances->create([
                'company_id' => $company->id,
                'branch_id' => $assignment?->branch_id,
                'user_id' => $user->id,
                'date' => $date,
                'status' => 'alpa',
                'late_minutes' => 0,
            ]);
            $marked++;
        }

        return $marked;
    }
}
