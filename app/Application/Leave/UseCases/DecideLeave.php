<?php

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Audit\Audit;
use App\Application\Hr\UseCases\ManageLeaveBalances;
use App\Application\Leave\DTOs\LeaveDTO;
use App\Application\Notify\Notifier;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DecideLeave
{
    public function __construct(
        private LeaveRepositoryInterface $leaves,
        private AttendanceRepositoryInterface $attendances,
        private AssignmentRepositoryInterface $assignments,
        private ManageLeaveBalances $balances,
    ) {}

    public function __invoke(Company $company, LeaveRequest $leave, User $decider, bool $approve): LeaveDTO
    {
        if ($leave->status !== 'diajukan') {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan sudah diproses.',
            ]);
        }

        return DB::transaction(function () use ($company, $leave, $decider, $approve) {
            $leave->status = $approve ? 'disetujui' : 'ditolak';
            $leave->decided_by = $decider->id;
            $this->leaves->save($leave);

            if ($approve) {
                if ($leave->type === 'cuti') {
                    $days = ManageLeaveBalances::inclusiveDays(
                        $leave->from_date->toDateString(),
                        $leave->to_date->toDateString(),
                    );
                    $this->balances->consume($company, $leave->user, (int) $leave->from_date->year, $days);
                }

                $this->fillAttendance($company, $leave);
            }

            Audit::record(
                $company, null, $approve ? 'leave.approve' : 'leave.reject', 'leave_request', $leave->id,
                ['status' => 'diajukan'],
                ['status' => $leave->status],
            );

            Notifier::toUser(
                $company,
                $leave->user,
                $approve ? 'cuti.disetujui' : 'cuti.ditolak',
                'Pengajuan '.$leave->type.' '.$leave->from_date->toDateString()
                    .' s/d '.$leave->to_date->toDateString().' '.($approve ? 'disetujui' : 'ditolak').'.',
                null,
                '/leaves',
            );

            return LeaveDTO::fromModel($leave);
        });
    }

    private function fillAttendance(Company $company, LeaveRequest $leave): void
    {
        $assignment = $this->assignments->paginateForCompany($company, 1, $leave->user_id, true)->first();

        foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $date) {
            $key = $date->toDateString();

            if ($this->attendances->existsForDay($leave->user, $key)) {
                continue;
            }

            $this->attendances->create([
                'company_id' => $company->id,
                'branch_id' => $assignment?->branch_id,
                'user_id' => $leave->user_id,
                'date' => $key,
                'status' => $leave->type,
                'late_minutes' => 0,
            ]);
        }
    }
}
