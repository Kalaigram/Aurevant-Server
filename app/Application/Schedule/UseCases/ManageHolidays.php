<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\Holiday;
use Illuminate\Pagination\LengthAwarePaginator;

final class ManageHolidays
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
    ) {}

    /** @return LengthAwarePaginator<int, Holiday> */
    public function list(?Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->schedules->paginateHolidays($company, $perPage);
    }

    public function create(?Company $company, array $attributes): Holiday
    {
        return $this->schedules->createHoliday($company, $attributes);
    }

    public function delete(?Company $company, int $id): void
    {
        if (! $this->schedules->deleteHoliday($company, $id)) {
            abort(404);
        }
    }
}
