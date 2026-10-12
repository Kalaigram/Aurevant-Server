<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Repositories;

use App\Models\Company;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Pagination\LengthAwarePaginator;

interface ScheduleRepositoryInterface
{
    /** @return LengthAwarePaginator<int, WorkShift> */
    public function paginateShifts(Company $company, int $perPage): LengthAwarePaginator;

    public function findShift(Company $company, int $id): ?WorkShift;

    public function createShift(Company $company, array $attributes): WorkShift;

    public function updateShift(WorkShift $shift, array $attributes): WorkShift;

    public function deleteShift(WorkShift $shift): void;

    /** @param array<int, int|null> $patterns weekday => shift_id atau null (libur) */
    public function replacePatterns(Company $company, User $user, array $patterns): void;

    /** @return array<int, ?WorkShift> keyed by weekday */
    public function patternsFor(User $user): array;

    public function upsertOverride(Company $company, User $user, string $date, ?int $shiftId, ?string $note): void;

    public function deleteOverride(Company $company, User $user, string $date): void;

    /** @return array<string, ?WorkShift> keyed by Y-m-d */
    public function overridesFor(User $user, string $from, string $to): array;

    /** @return LengthAwarePaginator<int, Holiday> */
    public function paginateHolidays(?Company $company, int $perPage): LengthAwarePaginator;

    public function createHoliday(?Company $company, array $attributes): Holiday;

    public function deleteHoliday(?Company $company, int $id): bool;

    /** @return list<string> tanggal Y-m-d libur (nasional + perusahaan) */
    public function holidayDates(?Company $company, string $from, string $to): array;
}
