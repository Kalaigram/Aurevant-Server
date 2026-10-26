<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);

        return [$owner, $staff, $company];
    }

    public function test_shift_pola_roster_dan_efektif(): void
    {
        [$owner, $staff, $company] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $this->postJson("{$base}/work-shifts", [])->assertUnauthorized();

        $pagi = $api()->postJson("{$base}/work-shifts", [
            'name' => 'Pagi', 'starts_at' => '08:00', 'ends_at' => '16:00', 'late_tolerance_minutes' => 15,
        ])->assertCreated()->assertJsonPath('data.overnight', false)->json('data.id');

        $malam = $api()->postJson("{$base}/work-shifts", [
            'name' => 'Malam', 'starts_at' => '22:00', 'ends_at' => '06:00',
        ])->assertCreated()->assertJsonPath('data.overnight', true)->json('data.id');

        // pola mingguan: Senin-Jumat pagi, weekend libur
        $api()->putJson("{$base}/users/{$staff->id}/schedule-pattern", [
            'patterns' => [null, $pagi, $pagi, $pagi, $pagi, $pagi, null],
        ])->assertOk();

        // Senin 2026-10-12 pola pagi
        $jadwal = $api()->getJson(
            "{$base}/users/{$staff->id}/schedule?from=2026-10-12&to=2026-10-18"
        )->assertOk()->json('data');
        $this->assertEquals('Pagi', $jadwal[0]['shiftName']);
        $this->assertEquals('pola', $jadwal[0]['source']);
        $this->assertTrue($jadwal[6]['isOff']);

        // roster Selasa jadi malam
        $api()->postJson("{$base}/users/{$staff->id}/schedule-overrides", [
            'date' => '2026-10-13', 'work_shift_id' => $malam,
        ])->assertOk();
        $jadwal = $api()->getJson(
            "{$base}/users/{$staff->id}/schedule?from=2026-10-13&to=2026-10-13"
        )->assertOk()->json('data');
        $this->assertEquals('Malam', $jadwal[0]['shiftName']);
        $this->assertEquals('roster', $jadwal[0]['source']);
        $this->assertTrue($jadwal[0]['overnight']);

        // libur perusahaan Rabu mengalahkan segalanya
        $api()->postJson("{$base}/holidays", ['date' => '2026-10-14', 'name' => 'Cuti Bersama'])
            ->assertCreated();
        $jadwal = $api()->getJson(
            "{$base}/users/{$staff->id}/schedule?from=2026-10-14&to=2026-10-14"
        )->assertOk()->json('data');
        $this->assertTrue($jadwal[0]['isOff']);
        $this->assertSame('libur', $jadwal[0]['source']);
    }

    public function test_izin_dan_libur_nasional(): void
    {
        [$owner, $staff, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        // tanpa izin shift.kelola: 403
        $this->actingAs($staff, 'sanctum')->postJson("{$base}/work-shifts", [
            'name' => 'X', 'starts_at' => '08:00', 'ends_at' => '16:00',
        ])->assertForbidden();

        // libur nasional hanya super admin
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/holidays', [
            'date' => '2026-12-25', 'name' => 'Natal',
        ])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/holidays', [
            'date' => '2026-12-25', 'name' => 'Natal',
        ])->assertCreated();

        // daftar libur nasional hanya untuk super admin
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/holidays')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/holidays')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Natal');

        // libur nasional berlaku untuk jadwal company
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $jadwal = $api()->getJson(
            "{$base}/users/{$staff->id}/schedule?from=2026-12-25&to=2026-12-25"
        )->assertOk()->json('data');
        $this->assertTrue($jadwal[0]['isOff']);
    }
}
