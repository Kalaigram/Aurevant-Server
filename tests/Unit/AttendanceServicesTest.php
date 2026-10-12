<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Attendance\Services\GeoService;
use App\Domain\Attendance\Services\QrTokenService;
use PHPUnit\Framework\TestCase;

class AttendanceServicesTest extends TestCase
{
    public function test_qr_berlaku_di_slot_sekarang_dan_sebelumnya(): void
    {
        $qr = new QrTokenService;
        $now = 1_000_000;

        $generated = $qr->generate('rahasia', 7, $now);
        $this->assertTrue($qr->validate('rahasia', 7, $generated['token'], $now));
        $this->assertGreaterThan(0, $generated['expires_in']);
        $this->assertLessThanOrEqual(QrTokenService::SLOT_SECONDS, $generated['expires_in']);

        // slot jauh di depan: tolak
        $this->assertFalse($qr->validate('rahasia', 7, $generated['token'], $now + 120));
        // cabang lain: tolak
        $this->assertFalse($qr->validate('rahasia', 8, $generated['token'], $now));
    }

    public function test_geofence_radius(): void
    {
        $geo = new GeoService;

        // Monas ke HI ~1,1 km
        $jarak = $geo->meters(-6.1754, 106.8272, -6.1823, 106.8345);
        $this->assertGreaterThan(900, $jarak);
        $this->assertLessThan(1500, $jarak);

        $this->assertTrue($geo->withinRadius(-6.1754, 106.8272, -6.1755, 106.8273, 100));
        $this->assertFalse($geo->withinRadius(-6.1754, 106.8272, -6.1823, 106.8345, 100));
    }
}
