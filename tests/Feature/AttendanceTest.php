<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create([
            'company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko',
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radius_m' => 100,
        ]);

        return [$owner, $staff, $company, $branch];
    }

    /** @return array{string, string} jam mulai-selesai yang aman dari tengah malam. */
    private function shiftWindow(): array
    {
        $anchor = now()->addHours(2);

        if ($anchor->isTomorrow()) {
            $anchor = now()->subHour();
        }

        return [$anchor->format('H:i'), $anchor->copy()->addHours(8)->format('H:i')];
    }

    private function shiftFor(User $actor, Company $company, string $startsAt, string $endsAt, int $tolerance = 0): int
    {
        return $this->actingAs($actor, 'sanctum')->postJson("/api/v1/companies/{$company->id}/work-shifts", [
            'name' => 'Shift', 'starts_at' => $startsAt, 'ends_at' => $endsAt,
            'late_tolerance_minutes' => $tolerance,
        ])->json('data.id');
    }

    private function patternAllWeek(User $actor, Company $company, User $staff, int $shiftId): void
    {
        $this->actingAs($actor, 'sanctum')->putJson(
            "/api/v1/companies/{$company->id}/users/{$staff->id}/schedule-pattern",
            ['patterns' => [$shiftId, $shiftId, $shiftId, $shiftId, $shiftId, $shiftId, $shiftId]]
        )->assertOk();
    }

    private function qr(User $actor, Company $company, Branch $branch): string
    {
        return $this->actingAs($actor, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/attendance-qr")
            ->assertOk()->json('data.token');
    }

    public function test_clock_in_out_lengkap(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}/attendance";

        $this->postJson("{$base}/clock-in", [])->assertUnauthorized();

        [$start, $end] = $this->shiftWindow();
        $this->shiftFor($owner, $company, $start, $end, 600);
        $shiftId = WorkShift::firstOrFail()->id;
        $this->patternAllWeek($owner, $company, $staff, $shiftId);

        $api = fn () => $this->actingAs($staff, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/attendance";

        $api()->postJson("{$base}/clock-in", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755,
            'lng' => 106.8273,
            'device_id' => 'hp-staff-1',
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertCreated()->assertJsonPath('data.status', 'hadir');

        // masuk dua kali: tolak
        $api()->postJson("{$base}/clock-in", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755,
            'lng' => 106.8273,
            'device_id' => 'hp-staff-1',
            'selfie' => UploadedFile::fake()->image('selfie2.jpg'),
        ])->assertUnprocessable();

        // pulang
        $pulang = $api()->postJson("{$base}/clock-out", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755,
            'lng' => 106.8273,
            'device_id' => 'hp-staff-1',
        ])->assertOk()->json('data');
        $this->assertNotNull($pulang['clockOutAt']);

        // riwayat saya
        $today = now()->toDateString();
        $api()->getJson("{$base}/me?from={$today}&to={$today}")
            ->assertOk()->assertJsonPath('meta.total', 1);

        // tim cabang (manajer = owner punya absensi.approve? owner punya semua)
        $apiOwner = fn () => $this->actingAs($owner, 'sanctum');
        $apiOwner()->getJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/attendance?date={$today}"
        )->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_qr_lokasi_perangkat_dan_scope(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        [$start, $end] = $this->shiftWindow();
        $this->shiftFor($owner, $company, $start, $end, 600);
        $shiftId = WorkShift::firstOrFail()->id;
        $this->patternAllWeek($owner, $company, $staff, $shiftId);

        $api = fn () => $this->actingAs($staff, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/attendance";
        $payload = fn () => [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755,
            'lng' => 106.8273,
            'device_id' => 'hp-staff-1',
            'selfie' => UploadedFile::fake()->image('s.jpg'),
        ];

        // QR ngawur
        $bad = $payload();
        $bad['qr_token'] = 'ngawur';
        $api()->postJson("{$base}/clock-in", $bad)->assertUnprocessable()
            ->assertJsonValidationErrors('qr_token');

        // lokasi jauh (HI, ~1km)
        $jauh = $payload();
        $jauh['lat'] = -6.1823;
        $jauh['lng'] = 106.8345;
        $api()->postJson("{$base}/clock-in", $jauh)->assertUnprocessable()
            ->assertJsonValidationErrors('location');

        // masuk OK (ikat hp-staff-1)
        $api()->postJson("{$base}/clock-in", $payload())->assertCreated();

        // HP lain tanpa reset: tolak
        $this->actingAs($staff, 'sanctum')->postJson("{$base}/clock-out", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755, 'lng' => 106.8273,
            'device_id' => 'hp-pinjaman',
        ])->assertUnprocessable()->assertJsonValidationErrors('device_id');

        // HR reset → HP baru bisa
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/companies/{$company->id}/users/{$staff->id}/device")
            ->assertOk();
        $this->actingAs($staff, 'sanctum')->postJson("{$base}/clock-out", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755, 'lng' => 106.8273,
            'device_id' => 'hp-baru',
        ])->assertOk();

        // cabang lain di luar scope penempatan: 403
        $cabangB = Branch::create(['company_id' => $company->id, 'code' => 'B', 'name' => 'Cabang B']);
        EmployeeAssignment::create([
            'user_id' => $staff->id, 'company_id' => $company->id,
            'branch_id' => $branch->id, 'started_at' => now()->toDateString(),
        ]);
        $this->actingAs($staff, 'sanctum')->postJson("{$base}/clock-in", [
            'branch_id' => $cabangB->id,
            'qr_token' => 'x',
            'lat' => -6.1755, 'lng' => 106.8273,
            'device_id' => 'hp-baru',
            'selfie' => UploadedFile::fake()->image('s.jpg'),
        ])->assertForbidden();
    }

    public function test_terlambat_dan_tanpa_jadwal(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        $start = now()->subHours(2)->format('H:i');
        $end = now()->addHours(6)->format('H:i');
        $this->shiftFor($owner, $company, $start, $end);
        $shiftId = WorkShift::firstOrFail()->id;
        $this->patternAllWeek($owner, $company, $staff, $shiftId);

        $api = fn () => $this->actingAs($staff, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/attendance";

        $masuk = $api()->postJson("{$base}/clock-in", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($staff, $company, $branch),
            'lat' => -6.1755, 'lng' => 106.8273,
            'device_id' => 'hp-1',
            'selfie' => UploadedFile::fake()->image('s.jpg'),
        ])->assertCreated()->json('data');
        $this->assertSame('terlambat', $masuk['status']);
        $this->assertGreaterThanOrEqual(100, $masuk['lateMinutes']);

        // user tanpa pola: tidak ada jadwal
        $magang = User::factory()->create();
        $this->actingAs($magang, 'sanctum')->postJson("{$base}/clock-in", [
            'branch_id' => $branch->id,
            'qr_token' => $this->qr($magang, $company, $branch),
            'lat' => -6.1755, 'lng' => 106.8273,
            'device_id' => 'hp-magang',
            'selfie' => UploadedFile::fake()->image('s.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('attendance');
    }
}
