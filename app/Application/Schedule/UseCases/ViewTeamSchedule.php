<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Models\Company;
use App\Models\EmployeeAssignment;

final class ViewTeamSchedule
{
    public function __construct(
        private ViewSchedule $view,
    ) {}

    /** @return list<array{userId: int, name: string, role: string|null, days: list<\App\Application\Schedule\DTOs\ScheduleDayDTO>}> */
    public function __invoke(Company $company, string $from, string $to): array
    {
        $assignments = EmployeeAssignment::query()
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->with(['user:id,name', 'role:id,name'])
            ->orderBy('user_id')
            ->get()
            ->unique('user_id')
            ->values();

        $rows = [];
        foreach ($assignments as $assignment) {
            if ($assignment->user === null) {
                continue;
            }

            $rows[] = [
                'userId' => $assignment->user_id,
                'name' => $assignment->user->name,
                'role' => $assignment->role?->name,
                'days' => ($this->view)($company, $assignment->user, $from, $to),
            ];
        }

        return $rows;
    }
}
