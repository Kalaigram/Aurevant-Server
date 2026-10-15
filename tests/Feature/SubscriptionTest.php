<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_buat_company_lengkap_dengan_trial(): void
    {
        $user = User::factory()->create();
        $api = fn () => $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/companies', [])->assertUnauthorized();

        $data = $api()->postJson('/api/v1/companies', [
            'name' => 'PT Maju',
            'branch_code' => 'PST',
            'branch_name' => 'Pusat',
        ])->assertCreated()->json('data');

        $company = Company::where('slug', 'pt-maju')->firstOrFail();
        $this->assertEquals('trial', $data['subscription']['status']);
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'code' => 'PST']);
        $this->assertDatabaseHas('roles', ['company_id' => $company->id, 'name' => 'Pemilik']);
        $this->assertDatabaseHas('employee_assignments', ['company_id' => $company->id, 'user_id' => $user->id]);

        // pemilik bisa langsung tambah cabang
        $api()->postJson("/api/v1/companies/{$company->id}/branches", [
            'code' => 'CB2', 'name' => 'Cabang 2',
        ])->assertCreated();
    }

    public function test_langganan_mati_memblokir_tulis(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $api = fn () => $this->actingAs($user, 'sanctum');

        Subscription::where('company_id', $company->id)->update(['status' => 'read_only']);

        $api()->postJson("/api/v1/companies/{$company->id}/branches", [
            'code' => 'X', 'name' => 'X',
        ])->assertForbidden()->assertJson([
            'status' => 'error', 'message' => 'Perusahaan atau langganan tidak aktif.', 'data' => null,
        ]);

        // baca tetap boleh
        $api()->getJson("/api/v1/companies/{$company->id}/branches")->assertOk();

        // masa tenggang: tulis boleh
        Subscription::where('company_id', $company->id)->update(['status' => 'tenggang']);
        $api()->postJson("/api/v1/companies/{$company->id}/branches", [
            'code' => 'X', 'name' => 'X',
        ])->assertCreated();
    }

    public function test_super_admin_kelola_paket_dan_status(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $apiAdmin = fn () => $this->actingAs($admin, 'sanctum');
        $apiUser = fn () => $this->actingAs($user, 'sanctum');

        $apiUser()->postJson('/api/v1/plans', ['name' => 'X'])->assertForbidden();

        $planId = $apiAdmin()->postJson('/api/v1/plans', [
            'name' => 'Enterprise', 'max_branches' => 50, 'max_employees' => 500, 'price' => 999000,
        ])->assertCreated()->assertJsonPath('data.name', 'Enterprise')->json('data.id');

        $apiAdmin()->getJson('/api/v1/plans')->assertOk()->assertJsonPath('meta.total', 2);

        $apiAdmin()->putJson("/api/v1/companies/{$company->id}/subscription", [
            'status' => 'aktif', 'plan_id' => $planId,
        ])->assertOk()->assertJsonPath('data.status', 'aktif');

        $apiUser()->putJson("/api/v1/companies/{$company->id}/subscription", ['status' => 'aktif'])
            ->assertForbidden();

        $apiAdmin()->getJson("/api/v1/companies/{$company->id}/subscription")
            ->assertOk()->assertJsonPath('data.planName', 'Enterprise');
    }
}
