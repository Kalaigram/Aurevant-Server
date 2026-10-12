<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Application\Attendance\DTOs\AttendanceDTO;
use App\Application\Audit\Audit;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceStatuses;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

final class CorrectAttendance
{
    public function __construct(
        private AttendanceRepositoryInterface $attendances,
    ) {}

    public function __invoke(Company $company, int $id, string $status, ?string $note): AttendanceDTO
    {
        if (! array_key_exists($status, AttendanceStatuses::all())) {
            throw ValidationException::withMessages([
                'status' => 'Status tidak dikenal.',
            ]);
        }

        $attendance = $this->attendances->findForCompany($company, $id) ?? abort(404);
        $before = $attendance->status;

        $attendance->status = $status;
        $attendance->note = $note;
        $saved = $this->attendances->save($attendance);

        Audit::record(
            $company, $attendance->branch_id, 'absensi.koreksi', 'attendance', $attendance->id,
            ['status' => $before],
            ['status' => $status, 'note' => $note],
        );

        return AttendanceDTO::fromModel($saved);
    }
}
