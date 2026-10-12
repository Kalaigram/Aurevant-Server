<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ManagePatterns
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
    ) {}

    /** @return list<array{weekday: int, shift_id: int|null}> */
    public function list(Company $company, User $user): array
    {
        $map = $this->schedules->patternsFor($user);
        $rows = [];

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $rows[] = ['weekday' => $weekday, 'shift_id' => $map[$weekday]?->id];
        }

        return $rows;
    }

    /** @param array<int, int|null> $patterns */
    public function replace(Company $company, User $user, array $patterns): void
    {
        foreach ($patterns as $shiftId) {
            if ($shiftId !== null && $this->schedules->findShift($company, $shiftId) === null) {
                throw ValidationException::withMessages([
                    'patterns' => 'Shift '.$shiftId.' bukan milik perusahaan ini.',
                ]);
            }
        }

        $this->schedules->replacePatterns($company, $user, $patterns);
    }
}
