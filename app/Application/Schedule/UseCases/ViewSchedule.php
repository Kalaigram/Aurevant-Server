<?php

declare(strict_types=1);

namespace App\Application\Schedule\UseCases;

use App\Application\Schedule\DTOs\ScheduleDayDTO;
use App\Domain\Schedule\Repositories\ScheduleRepositoryInterface;
use App\Domain\Schedule\Services\ScheduleResolver;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonPeriod;

final class ViewSchedule
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private ScheduleResolver $resolver,
    ) {}

    /** @return list<ScheduleDayDTO> */
    public function __invoke(Company $company, User $user, string $from, string $to): array
    {
        $patterns = $this->schedules->patternsFor($user);
        $overrides = $this->schedules->overridesFor($user, $from, $to);
        $holidays = $this->schedules->holidayDates($company, $from, $to);
        $holidaySet = array_flip($holidays);

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->toDateString();
            $shift = $patterns[$date->dayOfWeek] ?? null;

            $override = null;
            if (array_key_exists($key, $overrides)) {
                $overrideShift = $overrides[$key];
                $override = $overrideShift === null ? [] : [
                    'id' => $overrideShift->id,
                    'name' => $overrideShift->name,
                    'starts_at' => $overrideShift->starts_at->format('H:i'),
                    'ends_at' => $overrideShift->ends_at->format('H:i'),
                ];
            }

            $days[] = ScheduleDayDTO::fromEffective($this->resolver->resolve(
                $key,
                $date->dayOfWeek,
                $shift === null ? null : [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'starts_at' => $shift->starts_at->format('H:i'),
                    'ends_at' => $shift->ends_at->format('H:i'),
                ],
                $override,
                isset($holidaySet[$key]),
            ));
        }

        return $days;
    }
}
