<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class AlpaLeaveCardTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $shift = WorkShift::create([
            'company_id' => $company->id, 'name' => 'Pagi',
            'starts_at' => '08:00', 'ends_at' => '16:00',
        ]);

        return [$owner, $staff, $company, $branch, $shift];
    }

    public function test_alpa_izin_dan_koreksi(): void
    {
        [$owner, $staff, $company, $branch, $shift] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $api()->putJson(
            "/api/v1/companies/{$company->id}/users/{$staff->id}/schedule-pattern",
            ['patterns' => [null, $shift->id, $shift->id, $shift->id, $shift->id, $shift->id, null]]
        )->assertOk();
        // Senin 2026-10-12: staff terjadwal, tidak absen
        $this->artisan('attendance:mark-absent', [
            '--company' => $company->id, '--date' => '2026-10-12',
        ])->assertSuccessful();
        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id, 'date' => '2026-10-12', 'status' => 'alpa',
        ]);

        // Minggu libur: tidak ditandai
        $this->artisan('attendance:mark-absent', [
            '--company' => $company->id, '--date' => '2026-10-18',
        ])->assertSuccessful();
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $staff->id, 'date' => '2026-10-18',
        ]);

        // izin 2 hari → approve mengisi baris
        $cutiId = $this->actingAs($staff, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users/{$staff->id}/leaves",
            ['type' => 'cuti', 'from_date' => '2026-10-19', 'to_date' => '2026-10-20', 'reason' => 'Keluarga']
        )->assertCreated()->json('data.id');
        $api()->postJson("/api/v1/companies/{$company->id}/leaves/{$cutiId}/decide", ['approve' => true])
            ->assertOk()->assertJsonPath('data.status', 'disetujui');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $staff->id, 'date' => '2026-10-19', 'status' => 'cuti',
        ]);

        // alpa berjalan tak menimpa cuti
        $this->artisan('attendance:mark-absent', [
            '--company' => $company->id, '--date' => '2026-10-19',
        ])->assertSuccessful();
        $this->assertEquals(
            'cuti',
            Attendance::where('user_id', $staff->id)->where('date', '2026-10-19')->firstOrFail()->status
        );

        // koreksi alpa jadi sakit + tercatat audit
        $alpaId = Attendance::where('user_id', $staff->id)->where('date', '2026-10-12')->firstOrFail()->id;
        $api()->putJson("/api/v1/companies/{$company->id}/attendances/{$alpaId}", [
            'status' => 'sakit', 'note' => 'Ada surat dokter',
        ])->assertOk()->assertJsonPath('data.status', 'sakit');
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'action' => 'absensi.koreksi', 'entity_id' => $alpaId,
        ]);

        // tanpa izin koreksi: 403
        $this->actingAs($staff, 'sanctum')->putJson(
            "/api/v1/companies/{$company->id}/attendances/{$alpaId}",
            ['status' => 'hadir']
        )->assertForbidden();
    }

    public function test_kartu_karyawan(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');

        $this->getJson("/api/v1/companies/{$company->id}/users/{$staff->id}/card")
            ->assertUnauthorized();

        $kartu = $api()->getJson("/api/v1/companies/{$company->id}/users/{$staff->id}/card")
            ->assertOk()->json('data');
        $this->assertEquals($staff->name, $kartu['name']);
        $this->assertNotEmpty($kartu['employeeUuid']);
        $this->assertNotEmpty($kartu['qrContent']);

        $qr = $api()->get("/api/v1/companies/{$company->id}/users/{$staff->id}/card/qr")
            ->assertOk();
        $this->assertStringStartsWith('image/svg', $qr->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $qr->getContent());
    }
}
