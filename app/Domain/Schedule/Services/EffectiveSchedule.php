<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Services;

/** Hasil jadwal efektif satu tanggal. Murni, tanpa database. */
final readonly class EffectiveSchedule
{
    public function __construct(
        public string $date,
        public ?int $shiftId,
        public ?string $shiftName,
        public ?string $startsAt,
        public ?string $endsAt,
        public bool $overnight,
        public string $source,
    ) {}

    public function isOff(): bool
    {
        return $this->shiftId === null;
    }
}
