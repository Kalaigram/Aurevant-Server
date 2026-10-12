<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class AttendanceEloquentRepository implements AttendanceRepositoryInterface
{
    public function findForDay(User $user, Branch $branch, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('branch_id', $branch->id)
            ->where('date', $date)
            ->first();
    }

    public function findForCompany(Company $company, int $id): ?Attendance
    {
        return Attendance::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function existsForDay(User $user, string $date): bool
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->exists();
    }

    public function create(array $attributes): Attendance
    {
        return Attendance::query()->create($attributes)->refresh();
    }

    public function save(Attendance $attendance): Attendance
    {
        $attendance->save();

        return $attendance->refresh();
    }

    public function paginateForUser(User $user, int $perPage, string $from, string $to): LengthAwarePaginator
    {
        return Attendance::query()
            ->with('branch')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')
            ->paginate($perPage);
    }

    public function paginateForBranch(Branch $branch, int $perPage, string $date): LengthAwarePaginator
    {
        return Attendance::query()
            ->with('user')
            ->where('branch_id', $branch->id)
            ->where('date', $date)
            ->orderBy('clock_in_at')
            ->paginate($perPage);
    }
}
