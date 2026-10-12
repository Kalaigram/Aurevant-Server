<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface AttendanceRepositoryInterface
{
    public function findForDay(User $user, Branch $branch, string $date): ?Attendance;

    public function create(array $attributes): Attendance;

    public function save(Attendance $attendance): Attendance;

    /** @return LengthAwarePaginator<int, Attendance> */
    public function paginateForUser(User $user, int $perPage, string $from, string $to): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Attendance> */
    public function paginateForBranch(Branch $branch, int $perPage, string $date): LengthAwarePaginator;
}
