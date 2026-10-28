<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class SubscriptionLimitTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        return [$owner, $company, $branch];
    }

    public function test_kuota_cabang_dan_ringkasan_langganan(): void
    {
        [$owner, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        Branch::create(['company_id' => $company->id, 'code' => 'B2', 'name' => 'Cabang 2']);
        Branch::create(['company_id' => $company->id, 'code' => 'B3', 'name' => 'Cabang 3']);

        $this->actingAs($owner, 'sanctum')->getJson("{$base}/subscription")
            ->assertOk()
            ->assertJsonPath('data.maxBranches', 3)
            ->assertJsonPath('data.maxEmployees', 20)
            ->assertJsonPath('data.branchesUsed', 3)
            ->assertJsonPath('data.employeesUsed', 1);

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/branches", [
            'code' => 'B4',
            'name' => 'Cabang 4',
        ])->assertUnprocessable()->assertJsonValidationErrors('cabang');

        $this->actingAs($owner, 'sanctum')->putJson(
            "/api/v1/companies/{$company->id}/subscription",
            ['status' => 'aktif']
        )->assertForbidden();
    }

    public function test_kuota_karyawan_aktif_dan_penambahan_setelah_penempatan_berakhir(): void
    {
        [$owner, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $assignments = [];
        for ($i = 1; $i <= 19; $i++) {
            $assignments[] = $this->member(User::factory()->create(), $company);
        }

        $this->assertSame(20, EmployeeAssignment::query()
            ->where('company_id', $company->id)
            ->whereNull('ended_at')
            ->distinct()
            ->count('user_id'));

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/users", [
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('karyawan');

        $assignments[0]->update(['ended_at' => now()->toDateString()]);

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/users", [
            'name' => 'Karyawan Baru',
            'email' => 'baru@example.com',
        ])->assertCreated();
    }
}
