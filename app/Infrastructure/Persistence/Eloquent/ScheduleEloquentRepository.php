<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\ScheduleOverride;
use App\Models\SchedulePattern;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ScheduleEloquentRepository implements ScheduleRepositoryInterface
{
    public function paginateShifts(Company $company, int $perPage): LengthAwarePaginator
    {
        return WorkShift::query()
            ->where('company_id', $company->id)
            ->orderBy('starts_at')
            ->paginate($perPage);
    }

    public function findShift(Company $company, int $id): ?WorkShift
    {
        return WorkShift::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createShift(Company $company, array $attributes): WorkShift
    {
        return WorkShift::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function updateShift(WorkShift $shift, array $attributes): WorkShift
    {
        $shift->update($attributes);

        return $shift->refresh();
    }

    public function deleteShift(WorkShift $shift): void
    {
        $shift->delete();
    }

    public function replacePatterns(Company $company, User $user, array $patterns): void
    {
        DB::transaction(function () use ($company, $user, $patterns) {
            SchedulePattern::query()->where('user_id', $user->id)->delete();

            foreach ($patterns as $weekday => $shiftId) {
                SchedulePattern::query()->create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'weekday' => $weekday,
                    'work_shift_id' => $shiftId,
                ]);
            }
        });
    }

    public function patternsFor(User $user): array
    {
        $rows = SchedulePattern::query()
            ->with('shift')
            ->where('user_id', $user->id)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->weekday] = $row->shift;
        }

        return $map;
    }

    public function upsertOverride(Company $company, User $user, string $date, ?int $shiftId, ?string $note): void
    {
        ScheduleOverride::query()->updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            ['company_id' => $company->id, 'work_shift_id' => $shiftId, 'note' => $note]
        );
    }

    public function deleteOverride(Company $company, User $user, string $date): void
    {
        ScheduleOverride::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->delete();
    }

    public function overridesFor(User $user, string $from, string $to): array
    {
        $rows = ScheduleOverride::query()
            ->with('shift')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->date->toDateString()] = $row->shift;
        }

        return $map;
    }

    public function paginateHolidays(?Company $company, int $perPage): LengthAwarePaginator
    {
        return Holiday::query()
            ->when($company === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id)))
            ->orderBy('date')
            ->paginate($perPage);
    }

    public function createHoliday(?Company $company, array $attributes): Holiday
    {
        return Holiday::query()->create([
            'company_id' => $company?->id,
            ...$attributes,
        ])->refresh();
    }

    public function deleteHoliday(?Company $company, int $id): bool
    {
        return (bool) Holiday::query()
            ->when($company === null,
                fn ($query) => $query->whereNull('company_id')->whereKey($id),
                fn ($query) => $query->where('company_id', $company->id)->whereKey($id))
            ->delete();
    }

    public function holidayDates(?Company $company, string $from, string $to): array
    {
        return Holiday::query()
            ->when($company === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company->id)))
            ->whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }
}
