<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Schedule\Services\ScheduleResolver;
use PHPUnit\Framework\TestCase;

class ScheduleResolverTest extends TestCase
{
    public function test_pola_dan_libur(): void
    {
        $resolver = new ScheduleResolver;
        $pagi = ['id' => 1, 'name' => 'Pagi', 'starts_at' => '08:00', 'ends_at' => '16:00'];

        $kerja = $resolver->resolve('2026-10-12', 1, $pagi, null, false);
        $this->assertFalse($kerja->isOff());
        $this->assertSame('pola', $kerja->source);
        $this->assertFalse($kerja->overnight);

        $libur = $resolver->resolve('2026-10-18', 0, null, null, false);
        $this->assertTrue($libur->isOff());
    }

    public function test_override_menang_dari_pola_dan_libur_menang_dari_semua(): void
    {
        $resolver = new ScheduleResolver;
        $pagi = ['id' => 1, 'name' => 'Pagi', 'starts_at' => '08:00', 'ends_at' => '16:00'];
        $malam = ['id' => 2, 'name' => 'Malam', 'starts_at' => '22:00', 'ends_at' => '06:00'];

        $roster = $resolver->resolve('2026-10-13', 2, $pagi, $malam, false);
        $this->assertSame('roster', $roster->source);
        $this->assertSame(2, $roster->shiftId);
        $this->assertTrue($roster->overnight);

        $rosterLibur = $resolver->resolve('2026-10-14', 3, $pagi, [], false);
        $this->assertTrue($rosterLibur->isOff());
        $this->assertSame('roster', $rosterLibur->source);

        $liburNasional = $resolver->resolve('2026-10-15', 4, $pagi, $malam, true);
        $this->assertTrue($liburNasional->isOff());
        $this->assertSame('libur', $liburNasional->source);
    }
}
