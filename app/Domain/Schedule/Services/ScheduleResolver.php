<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Services;

/**
 * Jadwal efektif: libur > override tanggal > pola mingguan.
 * Murni (tanpa Laravel), bisa di-unit-test langsung.
 */
final class ScheduleResolver
{
    /**
     * @param  array{starts_at: string, ends_at: string}|null  $pattern
     * @param  array{id: int, name: string, starts_at: string, ends_at: string}|null  $override
     */
    public function resolve(
        string $date,
        int $weekday,
        ?array $pattern,
        ?array $override,
        bool $isHoliday,
    ): EffectiveSchedule {
        if ($isHoliday) {
            return new EffectiveSchedule($date, null, null, null, null, false, 'libur');
        }

        if ($override !== null) {
            if (! isset($override['id'])) {
                return new EffectiveSchedule($date, null, null, null, null, false, 'roster');
            }

            return new EffectiveSchedule(
                $date,
                $override['id'],
                $override['name'],
                $override['starts_at'],
                $override['ends_at'],
                $override['ends_at'] <= $override['starts_at'],
                'roster',
            );
        }

        if ($pattern === null) {
            return new EffectiveSchedule($date, null, null, null, null, false, 'libur');
        }

        return new EffectiveSchedule(
            $date,
            $pattern['id'] ?? null,
            $pattern['name'] ?? null,
            $pattern['starts_at'],
            $pattern['ends_at'],
            $pattern['ends_at'] <= $pattern['starts_at'],
            'pola',
        );
    }
}
