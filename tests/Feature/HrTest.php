<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class HrTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: User, 2: User, 3: Company, 4: Branch} */
    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create(['name' => 'Siti']);
        $other = User::factory()->create(['name' => 'Budi']);
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $this->member($staff, $company, $branch->id);
        $this->member($other, $company, $branch->id);

        return [$owner, $staff, $other, $company, $branch];
    }

    public function test_saldo_cuti_dan_pemakaian_saat_disetujui(): void
    {
        [$owner, $staff, , $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $this->putJson("{$base}/users/{$staff->id}/leave-balance", [])->assertUnauthorized();

        $this->actingAs($owner, 'sanctum')->putJson("{$base}/users/{$staff->id}/leave-balance", [
            'year' => 2026,
            'entitlement' => 10,
            'carryover' => 2,
        ])->assertOk()
            ->assertJsonPath('data.userId', $staff->id)
            ->assertJsonPath('data.available', '12.00');

        $this->actingAs($staff, 'sanctum')->getJson("{$base}/leave-balances?year=2026")
            ->assertOk()
            ->assertJsonPath('data.0.userId', $staff->id)
            ->assertJsonPath('data.0.available', '12.00');

        $leaveId = $this->actingAs($staff, 'sanctum')->postJson(
            "{$base}/users/{$staff->id}/leaves",
            ['type' => 'cuti', 'from_date' => '2026-12-28', 'to_date' => '2026-12-31', 'reason' => 'Keluarga']
        )->assertCreated()->json('data.id');

        $this->assertDatabaseHas('leave_balances', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'year' => 2026,
            'used' => 0,
        ]);

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/leaves/{$leaveId}/decide", ['approve' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('leave_balances', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'year' => 2026,
            'used' => 4,
        ]);

        $this->actingAs($staff, 'sanctum')->getJson("{$base}/leave-balances?year=2026")
            ->assertOk()
            ->assertJsonPath('data.0.available', '8.00');

        $this->actingAs($staff, 'sanctum')->postJson(
            "{$base}/users/{$staff->id}/leaves",
            ['type' => 'cuti', 'from_date' => '2026-12-01', 'to_date' => '2026-12-10', 'reason' => 'Keperluan']
        )->assertUnprocessable()->assertJsonValidationErrors('saldo_cuti');
    }

    public function test_lembur_diajukan_dan_diputuskan(): void
    {
        [$owner, $staff, $other, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $id = $this->actingAs($staff, 'sanctum')->postJson("{$base}/overtime-requests", [
            'date' => '2026-10-30',
            'start_time' => '17:00',
            'end_time' => '19:30',
            'reason' => 'Stok opname',
        ])->assertCreated()
            ->assertJsonPath('data.minutes', 150)
            ->assertJsonPath('data.status', 'diajukan')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->getJson("{$base}/overtime-requests?status=diajukan&user_id={$staff->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($staff, 'sanctum')->postJson("{$base}/overtime-requests", [
            'user_id' => $other->id,
            'date' => '2026-10-31',
            'start_time' => '17:00',
            'end_time' => '18:00',
            'reason' => 'Membantu',
        ])->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->postJson("{$base}/overtime-requests/{$id}/decide", ['approve' => true])
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/overtime-requests/{$id}/decide", ['approve' => true, 'note' => 'Disetujui'])
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/overtime-requests/{$id}/decide", ['approve' => false])
            ->assertUnprocessable();

        $this->assertDatabaseHas('notifications', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'type' => 'lembur.disetujui',
        ]);
    }

    public function test_tukar_shift_disetujui_membuat_override(): void
    {
        [$owner, $staff, $other, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $pagi = WorkShift::create([
            'company_id' => $company->id, 'name' => 'Pagi',
            'starts_at' => '08:00', 'ends_at' => '16:00',
        ]);
        $malam = WorkShift::create([
            'company_id' => $company->id, 'name' => 'Malam',
            'starts_at' => '16:00', 'ends_at' => '23:00',
        ]);

        $id = $this->actingAs($staff, 'sanctum')->postJson("{$base}/shift-swaps", [
            'target_user_id' => $other->id,
            'date' => '2026-10-30',
            'requester_shift_id' => $pagi->id,
            'target_shift_id' => $malam->id,
            'note' => 'Ada acara keluarga',
        ])->assertCreated()
            ->assertJsonPath('data.requesterShiftName', 'Pagi')
            ->assertJsonPath('data.targetShiftName', 'Malam')
            ->json('data.id');

        $this->actingAs($staff, 'sanctum')
            ->postJson("{$base}/shift-swaps/{$id}/decide", ['approve' => true])
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/shift-swaps/{$id}/decide", ['approve' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $this->assertDatabaseHas('schedule_overrides', [
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'date' => '2026-10-30',
            'work_shift_id' => $malam->id,
        ]);
        $this->assertDatabaseHas('schedule_overrides', [
            'company_id' => $company->id,
            'user_id' => $other->id,
            'date' => '2026-10-30',
            'work_shift_id' => $pagi->id,
        ]);
    }
}
