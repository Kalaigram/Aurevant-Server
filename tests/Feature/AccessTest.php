<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        return [$owner, $staff, $company, $other, $branch];
    }

    public function test_izin_saya_perusahaan(): void
    {
        [$owner, $staff, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}/access";

        $this->getJson("{$base}/my-permissions")->assertUnauthorized();

        $mine = $this->actingAs($owner, 'sanctum')->getJson("{$base}/my-permissions")->assertOk()->json('data');
        $this->assertContains('transaksi.void', $mine['permissions']);
        $this->assertContains('jasa.proses', $mine['permissions']);

        $staffMine = $this->actingAs($staff, 'sanctum')->getJson("{$base}/my-permissions")->assertOk()->json('data');
        $this->assertSame([], $staffMine['permissions']);
    }

    public function test_katalog_dan_role_dari_template(): void
    {
        [$owner, $staff, $company] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/access";

        $this->postJson("{$base}/roles", [])->assertUnauthorized();

        $api()->getJson("{$base}/permissions")->assertOk()->assertJsonPath('status', 'success');
        $katalog = $api()->getJson("{$base}/permissions")->json('data');
        $this->assertContains('transaksi.void', $katalog);
        $this->assertGreaterThanOrEqual(30, count($katalog));

        $kasir = $api()->postJson("{$base}/roles", [
            'name' => 'Kasir', 'template' => 'Kasir',
        ])->assertCreated()->json('data');
        $this->assertEqualsCanonicalizing(
            ['transaksi.buat', 'nota.lihat', 'shift.buka', 'shift.tutup', 'pelanggan.lihat', 'gaji.lihat_sendiri'],
            $kasir['permissions']
        );

        // permission di luar katalog: tolak
        $api()->postJson("{$base}/roles", [
            'name' => 'Hacker', 'permissions' => ['server.hapus'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_assign_revoke_dan_isolasi_team(): void
    {
        [$owner, $staff, $company, $other] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/access";

        $api()->postJson("{$base}/roles", [
            'name' => 'Kasir', 'permissions' => ['transaksi.buat', 'nota.lihat'],
        ])->assertCreated();

        $api()->postJson("{$base}/users/{$staff->id}/roles", ['role' => 'Kasir'])
            ->assertOk();

        setPermissionsTeamId($company->id);
        $this->assertTrue($staff->hasPermissionTo('transaksi.buat'));
        $this->assertFalse($staff->hasPermissionTo('transaksi.void'));

        // di team lain tidak berlaku
        setPermissionsTeamId($other->id);
        $staff = $staff->fresh();
        $this->assertFalse($staff->hasPermissionTo('transaksi.buat'));

        // cabut
        $api()->deleteJson("{$base}/users/{$staff->id}/roles/Kasir")->assertOk();
        $staff = $staff->fresh();
        setPermissionsTeamId($company->id);
        $this->assertFalse($staff->hasPermissionTo('transaksi.buat'));

        // role perusahaan lain tak terlihat
        $api()->getJson("/api/v1/companies/{$other->id}/access/roles")
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_penempatan_dengan_riwayat(): void
    {
        [$owner, $staff, $company, $other, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/access";

        $roleId = $api()->postJson("{$base}/roles", [
            'name' => 'Kasir', 'permissions' => ['transaksi.buat'],
        ])->json('data.id');

        $tugasId = $api()->postJson("{$base}/users/{$staff->id}/assignments", [
            'branch_id' => $branch->id,
            'role_id' => $roleId,
            'started_at' => '2026-10-01',
        ])->assertCreated()->assertJsonPath('data.roleName', 'Kasir')->json('data.id');

        // cabang perusahaan lain: 404
        $otherBranch = Branch::create(['company_id' => $other->id, 'code' => 'HQ', 'name' => 'Pusat']);
        $api()->postJson("{$base}/users/{$staff->id}/assignments", [
            'branch_id' => $otherBranch->id,
            'started_at' => '2026-10-01',
        ])->assertNotFound();

        $api()->getJson("{$base}/assignments?active=1")->assertOk()->assertJsonPath('meta.total', 1);
        $api()->getJson("{$base}/assignments?active=1&q=".urlencode($staff->name))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.userName', $staff->name)
            ->assertJsonPath('data.0.userEmail', $staff->email)
            ->assertJsonPath('data.0.branchCode', 'TKO')
            ->assertJsonPath('data.0.branchName', 'Toko');
        $api()->getJson("{$base}/assignments?active=1&branch_id={$branch->id}&role_id={$roleId}")
            ->assertOk()->assertJsonPath('meta.total', 1);
        $api()->getJson("{$base}/assignments?active=1&q=tidak-ada")->assertOk()->assertJsonPath('meta.total', 0);
        $api()->postJson("{$base}/assignments/{$tugasId}/close", ['ended_at' => '2026-10-31'])
            ->assertOk()->assertJsonPath('data.endedAt', '2026-10-31');
        $api()->getJson("{$base}/assignments?active=1")->assertOk()->assertJsonPath('meta.total', 0);
        // riwayat tetap ada
        $api()->getJson("{$base}/assignments")->assertOk()->assertJsonPath('meta.total', 1);
    }
}
