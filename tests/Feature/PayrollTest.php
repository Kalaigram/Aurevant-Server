<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeSalary;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\SalaryComponent;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class PayrollTest extends TestCase
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
        $this->member($staff, $company, $branch->id);

        $ids = [];
        foreach ([
            ['name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed', 'deductible' => true],
            ['name' => 'Tunjangan tetap', 'calc_type' => 'monthly_fixed', 'deductible' => true],
            ['name' => 'Uang makan', 'calc_type' => 'daily_rate', 'deductible' => false],
            ['name' => 'Komisi jasa', 'calc_type' => 'commission', 'deductible' => false],
        ] as $component) {
            $ids[$component['name']] = SalaryComponent::create(
                ['company_id' => $company->id] + $component
            )->id;
        }

        $amounts = ['Gaji pokok' => 3000000, 'Tunjangan tetap' => 600000, 'Uang makan' => 25000, 'Komisi jasa' => 0];
        foreach ($ids as $name => $id) {
            EmployeeSalary::create([
                'company_id' => $company->id, 'user_id' => $staff->id,
                'salary_component_id' => $id, 'amount' => $amounts[$name],
            ]);
        }

        for ($day = 1; $day <= 22; $day++) {
            Attendance::create([
                'company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $staff->id,
                'date' => sprintf('2026-10-%02d', $day), 'status' => 'hadir',
            ]);
        }
        Attendance::create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $staff->id,
            'date' => '2026-10-23', 'status' => 'alpa',
        ]);

        return [$owner, $staff, $company, $branch];
    }

    public function test_hitung_kunci_bayar_sesuai_spek(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $this->postJson("{$base}/payroll-periods", [])->assertUnauthorized();

        $periodId = $api()->postJson("{$base}/payroll-periods", [
            'name' => 'Oktober 2026', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'divisor' => 30,
        ])->assertCreated()->json('data.id');

        $api()->getJson("{$base}/payroll-periods?q=oktober&status=draft")->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $periodId)
            ->assertJsonPath('data.0.from', '2026-10-01')
            ->assertJsonPath('data.0.to', '2026-10-31');
        $api()->getJson("{$base}/payroll-periods?status=dibayar")->assertOk()->assertJsonPath('meta.total', 0);
        $api()->getJson("{$base}/payroll-periods/{$periodId}")->assertOk()
            ->assertJsonPath('data.period.from', '2026-10-01');

        $hitung = $api()->postJson("{$base}/payroll-periods/{$periodId}/calculate")
            ->assertOk()->json('data');
        $this->assertEquals(4030000.0, $hitung['employees'][0]['net']);

        $slip = $api()->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertOk()->json('data');
        $labels = collect($slip['lines'])->pluck('amount', 'label')->all();
        $this->assertEquals('3000000.00', $labels['Gaji pokok']);
        $this->assertEquals('-100000.00', $labels['Potongan alpa - Gaji pokok']);
        $this->assertEquals('550000.00', $labels['Uang makan']);
        $this->assertEquals('4030000.00', $slip['net']);

        // komisi dari order jasa yang diselesaikan staff
        $servis = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-01', 'name' => 'Servis',
            'type' => 'jasa', 'track_stock' => false,
            'commission_type' => 'nominal', 'commission_value' => 50000,
        ]);
        ItemPrice::create(['item_id' => $servis->id, 'branch_id' => $branch->id, 'price' => 100000]);
        $orderId = $api()->postJson("{$base}/branches/{$branch->id}/service-orders", [
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/branches/{$branch->id}/service-orders/{$orderId}/process", ['assignee_id' => $staff->id])->assertOk();
        $api()->postJson("{$base}/branches/{$branch->id}/service-orders/{$orderId}/complete")->assertOk();
        ServiceOrder::whereKey($orderId)->update(['updated_at' => '2026-10-15 10:00:00']);

        // bonus manual bertahan setelah hitung ulang
        $api()->postJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/adjustments", [
            'label' => 'Bonus target', 'amount' => 25000,
        ])->assertCreated();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/calculate")->assertOk();
        $slip = $api()->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertOk()->json('data');
        $this->assertEquals('4105000.00', $slip['net']);

        // review → setujui (kunci) → bayar
        $api()->postJson("{$base}/payroll-periods/{$periodId}/review")->assertOk();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/calculate")->assertOk();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/approve")->assertOk()
            ->assertJsonPath('data.status', 'disetujui');
        $api()->postJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/adjustments", [
            'label' => 'Susulan', 'amount' => 1000,
        ])->assertUnprocessable();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/pay")->assertOk()
            ->assertJsonPath('data.status', 'dibayar');
    }

    public function test_komponen_crud_dan_assign(): void
    {
        [$owner, $staff, $company] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $id = $api()->postJson("{$base}/salary-components", [
            'name' => 'Lembur', 'calc_type' => 'manual',
        ])->assertCreated()->json('data.id');

        $api()->getJson("{$base}/salary-components?q=lembur&active=1")->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.taxable', false)
            ->assertJsonPath('data.0.bpjsBase', false)
            ->assertJsonPath('data.0.active', true);
        $api()->getJson("{$base}/salary-components?active=0")->assertOk()->assertJsonPath('meta.total', 0);

        $api()->postJson("{$base}/users/{$staff->id}/salary-components", [
            'component_id' => $id, 'amount' => 100000,
        ])->assertOk();

        // tanpa izin gaji.kelola: tolak
        $this->actingAs($staff, 'sanctum')->postJson("{$base}/salary-components", [
            'name' => 'X', 'calc_type' => 'manual',
        ])->assertForbidden();
    }

    public function test_slip_milik_sendiri_dan_izin(): void
    {
        [$owner, $staff, $company, $branch] = $this->setupWorld();
        $manajer = User::factory()->create();
        $this->member($manajer, $company, $branch->id);
        $base = "/api/v1/companies/{$company->id}";

        $periodId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/payroll-periods", [
            'name' => 'Oktober 2026', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31',
        ])->assertCreated()->json('data.id');

        // staff tanpa izin apa pun: slip sendiri pun ditolak
        $this->actingAs($staff, 'sanctum')->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertForbidden();

        // staff dengan gaji.lihat_sendiri: slip sendiri boleh
        $this->beriIzin($staff, $company, ['gaji.lihat_sendiri']);
        $this->actingAs($staff->fresh(), 'sanctum')->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertOk();

        // slip orang lain: tetap tolak
        $this->actingAs($staff->fresh(), 'sanctum')->getJson("{$base}/payroll-periods/{$periodId}/users/{$owner->id}/slip")
            ->assertForbidden();

        // orang lain intip: tolak
        $this->actingAs($manajer, 'sanctum')->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertForbidden();

        // owner (gaji.kelola): boleh
        $this->actingAs($owner, 'sanctum')->getJson("{$base}/payroll-periods/{$periodId}/users/{$staff->id}/slip")
            ->assertOk();

        // tanpa izin kelola: daftar periode ditolak
        $this->actingAs($manajer, 'sanctum')->getJson("{$base}/payroll-periods")
            ->assertForbidden();
    }

    /** @param list<string> $perms */
    private function beriIzin(User $user, Company $company, array $perms): void
    {
        setPermissionsTeamId($company->id);
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(
            ['name' => 'Terbatas', 'guard_name' => 'web', 'company_id' => $company->id],
        );
        $role->syncPermissions($perms);
        $user->assignRole($role);
    }
}
