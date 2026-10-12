<?php

declare(strict_types=1);

namespace App\Application\Schedule\DTOs;

use App\Domain\Schedule\Services\EffectiveSchedule;

final readonly class ScheduleDayDTO
{
    public function __construct(
        public string $date,
        public ?int $shiftId,
        public ?string $shiftName,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $overnight,
        public string $source,
        public bool $isOff,
    ) {}

    public static function fromEffective(EffectiveSchedule $schedule): self
    {
        return new self(
            date: $schedule->date,
            shiftId: $schedule->shiftId,
            shiftName: $schedule->shiftName,
            startsAt: $schedule->startsAt,
            endsAt: $schedule->endsAt,
            overnight: $schedule->overnight,
            source: $schedule->source,
            isOff: $schedule->isOff(),
        );
    }
}
