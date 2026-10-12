<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function auth(): User
    {
        return User::factory()->create();
    }

    public function test_guest_ditolak(): void
    {
        $company = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);

        $this->getJson("/api/v1/companies/{$company->id}/branches")->assertUnauthorized();
    }

    public function test_index_hanya_cabang_perusahaan_sendiri(): void
    {
        $user = $this->auth();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        Branch::create(['company_id' => $a->id, 'code' => 'A1', 'name' => 'Cabang A1']);
        Branch::create(['company_id' => $b->id, 'code' => 'B1', 'name' => 'Cabang B1']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$a->id}/branches");

        $response->assertOk()->assertJson([
            'status' => 'success',
            'meta' => ['total' => 1],
        ]);
        $this->assertEquals(['Cabang A1'], $response->json('data.*.name'));
    }

    public function test_store_dan_validasi(): void
    {
        $user = $this->auth();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $api()->postJson("/api/v1/companies/{$a->id}/branches", [
            'code' => 'SBY-01',
            'name' => 'Cabang Surabaya Barat',
            'type' => 'cabang',
            'timezone' => 'Asia/Jakarta',
        ])->assertCreated()->assertJsonPath('data.code', 'SBY-01');

        // kode sama di perusahaan beda: boleh
        $api()->postJson("/api/v1/companies/{$b->id}/branches", [
            'code' => 'SBY-01',
            'name' => 'Cabang SBY milik B',
        ])->assertCreated();

        // kode sama di perusahaan sama: tolak
        $api()->postJson("/api/v1/companies/{$a->id}/branches", [
            'code' => 'SBY-01',
            'name' => 'Duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        // timezone ngawur: tolak
        $api()->postJson("/api/v1/companies/{$a->id}/branches", [
            'code' => 'SBY-02',
            'name' => 'Cabang X',
            'timezone' => 'Mars/Olympus',
        ])->assertUnprocessable()->assertJsonValidationErrors('timezone');
    }

    public function test_show_update_dan_soft_delete(): void
    {
        $user = $this->auth();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        $branch = Branch::create(['company_id' => $a->id, 'code' => 'A1', 'name' => 'Lama']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $api()->getJson("/api/v1/companies/{$a->id}/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Lama');

        // intip dari perusahaan lain: 404
        $api()->getJson("/api/v1/companies/{$b->id}/branches/{$branch->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 'error');

        $api()->putJson("/api/v1/companies/{$a->id}/branches/{$branch->id}", [
            'code' => 'A1',
            'name' => 'Baru',
        ])->assertOk()->assertJsonPath('data.name', 'Baru');

        $api()->deleteJson("/api/v1/companies/{$a->id}/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $api()->getJson("/api/v1/companies/{$a->id}/branches/{$branch->id}")->assertNotFound();
    }
}
