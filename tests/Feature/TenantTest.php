<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        return [$admin, $user, $company];
    }

    public function test_daftar_detail_dan_tangguhkan(): void
    {
        [$admin, $user, $company] = $this->setupWorld();
        $boss = fn () => $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/admin/companies')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/companies')->assertForbidden();

        $list = $boss()->getJson('/api/v1/admin/companies?search=pt-a')->assertOk()->json('data');
        $this->assertSame('PT A', $list[0]['name']);
        $this->assertSame(1, $list[0]['branchesCount']);

        $boss()->getJson('/api/v1/admin/companies?status=diarsipkan')->assertOk()
            ->assertJsonPath('meta.total', 0);

        $detail = $boss()->getJson("/api/v1/admin/companies/{$company->id}")->assertOk()->json('data');
        $this->assertSame('aktif', $detail['status']);

        // tangguhkan: tulis diblokir, baca boleh
        $boss()->patchJson("/api/v1/admin/companies/{$company->id}", ['status' => 'ditangguhkan'])
            ->assertOk()->assertJsonPath('data.status', 'ditangguhkan');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/expense-categories", ['name' => 'X'])
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/expense-categories")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'action' => 'tenant.tangguhkan',
        ]);

        // aktifkan lagi: tulis jalan
        $boss()->patchJson("/api/v1/admin/companies/{$company->id}", ['status' => 'aktif'])->assertOk();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/expense-categories", ['name' => 'X'])
            ->assertCreated();

        // status ngawur: tolak
        $boss()->patchJson("/api/v1/admin/companies/{$company->id}", ['status' => 'hilang'])
            ->assertUnprocessable();
        $boss()->getJson('/api/v1/admin/companies/999999')->assertNotFound();
    }
}
