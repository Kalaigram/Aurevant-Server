<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Schedule\DTOs\HolidayDTO;
use App\Application\Schedule\UseCases\ManageHolidays;
use App\Application\Schedule\UseCases\ManageOverrides;
use App\Application\Schedule\UseCases\ManagePatterns;
use App\Application\Schedule\UseCases\ManageShifts;
use App\Application\Schedule\UseCases\ViewSchedule;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function shifts(Request $request, Company $company, ManageShifts $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->list($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar shift.'
        );
    }

    public function storeShift(Request $request, Company $company, ManageShifts $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase->create($company, $request->validate($this->shiftRules())),
            'Shift dibuat.',
            201
        );
    }

    public function updateShift(Request $request, Company $company, int $shift, ScheduleRepositoryInterface $schedules, ManageShifts $useCase): JsonResponse
    {
        $found = $schedules->findShift($company, $shift) ?? abort(404);

        return ApiResponse::success(
            $useCase->update($found, $request->validate($this->shiftRules())),
            'Shift diperbarui.'
        );
    }

    public function destroyShift(Company $company, int $shift, ScheduleRepositoryInterface $schedules, ManageShifts $useCase): JsonResponse
    {
        $found = $schedules->findShift($company, $shift) ?? abort(404);
        $useCase->delete($found);

        return ApiResponse::success(null, 'Shift dihapus.');
    }

    public function patterns(Company $company, User $user, ManagePatterns $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->list($company, $user), 'Pola mingguan.');
    }

    public function replacePatterns(Request $request, Company $company, User $user, ManagePatterns $useCase): JsonResponse
    {
        $validated = $request->validate([
            'patterns' => ['required', 'array', 'size:7'],
            'patterns.*' => ['nullable', 'integer', 'exists:work_shifts,id'],
        ]);

        $useCase->replace($company, $user, array_map(
            fn ($shiftId) => $shiftId === null ? null : (int) $shiftId,
            array_values($validated['patterns'])
        ));

        return ApiResponse::success($useCase->list($company, $user), 'Pola disimpan.');
    }

    public function upsertOverride(Request $request, Company $company, User $user, ManageOverrides $useCase): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'work_shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $useCase->upsert(
            $company,
            $user,
            $validated['date'],
            isset($validated['work_shift_id']) ? (int) $validated['work_shift_id'] : null,
            $validated['note'] ?? null,
        );

        return ApiResponse::success(null, 'Roster disimpan.');
    }

    public function deleteOverride(Request $request, Company $company, User $user, ManageOverrides $useCase): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $useCase->delete($company, $user, $validated['date']);

        return ApiResponse::success(null, 'Roster dihapus.');
    }

    public function view(Request $request, Company $company, User $user, ViewSchedule $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::success(
            $useCase($company, $user, $validated['from'], $validated['to']),
            'Jadwal efektif.'
        );
    }

    public function holidays(Request $request, Company $company, ManageHolidays $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->list($company, (int) ($validated['per_page'] ?? 15))->through(
                fn ($holiday) => HolidayDTO::fromModel($holiday)
            ),
            'Daftar libur.'
        );
    }

    public function storeHoliday(Request $request, Company $company, ManageHolidays $useCase): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return ApiResponse::success(
            HolidayDTO::fromModel($useCase->create($company, $validated)),
            'Libur ditambah.',
            201
        );
    }

    public function destroyHoliday(Company $company, int $holiday, ManageHolidays $useCase): JsonResponse
    {
        $useCase->delete($company, $holiday);

        return ApiResponse::success(null, 'Libur dihapus.');
    }

    public function storeNationalHoliday(Request $request, ManageHolidays $useCase): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return ApiResponse::success(
            HolidayDTO::fromModel($useCase->create(null, $validated)),
            'Libur nasional ditambah.',
            201
        );
    }

    public function destroyNationalHoliday(int $holiday, ManageHolidays $useCase): JsonResponse
    {
        $useCase->delete(null, $holiday);

        return ApiResponse::success(null, 'Libur nasional dihapus.');
    }

    /** @return array<string, mixed> */
    private function shiftRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:600'],
        ];
    }
}
