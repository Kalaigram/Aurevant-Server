<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\User;

final class ManageOverrides
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
    ) {}

    public function upsert(Company $company, User $user, string $date, ?int $shiftId, ?string $note): void
    {
        if ($shiftId !== null && $this->schedules->findShift($company, $shiftId) === null) {
            abort(404);
        }

        $this->schedules->upsertOverride($company, $user, $date, $shiftId, $note);
    }

    public function delete(Company $company, User $user, string $date): void
    {
        $this->schedules->deleteOverride($company, $user, $date);
    }
}
