<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Application\Schedule\DTOs\WorkShiftDTO;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\WorkShift;
use Illuminate\Pagination\LengthAwarePaginator;

final class ManageShifts
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
    ) {}

    /** @return LengthAwarePaginator<int, WorkShiftDTO> */
    public function list(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->schedules->paginateShifts($company, $perPage)
            ->through(fn ($shift) => WorkShiftDTO::fromModel($shift));
    }

    public function create(Company $company, array $attributes): WorkShiftDTO
    {
        return WorkShiftDTO::fromModel($this->schedules->createShift($company, $attributes));
    }

    public function update(WorkShift $shift, array $attributes): WorkShiftDTO
    {
        return WorkShiftDTO::fromModel($this->schedules->updateShift($shift, $attributes));
    }

    public function delete(WorkShift $shift): void
    {
        $this->schedules->deleteShift($shift);
    }
}
