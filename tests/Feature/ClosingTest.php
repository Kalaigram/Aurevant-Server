<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Finance\Repositories\ExpenseRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ClosingTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $catId = app(ExpenseRepositoryInterface::class)->createCategory($company, ['name' => 'Operasional'])->id;

        return [$user, $company, $branch, $catId];
    }

    private function draftExpense(object $api, int $companyId, int $branchId, int $catId, string $date): int
    {
        return $api->postJson("/api/v1/companies/{$companyId}/expenses", [
            'branch_id' => $branchId,
            'expense_category_id' => $catId,
            'amount' => 100000,
            'date' => $date,
            'description' => 'Uji tutup periode',
        ])->assertCreated()->json('data.id');
    }

    public function test_tutup_blokir_posting_dan_buka_lagi(): void
    {
        [$user, $company, $branch, $catId] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $this->postJson("{$base}/accounting-periods/close", [])->assertUnauthorized();

        $api = fn () => $this->actingAs($user, 'sanctum');
        $today = now()->toDateString();

        $this->draftExpense($api(), $company->id, $branch->id, $catId, $today);

        // masih ada draft: tutup ditolak
        $api()->postJson("{$base}/accounting-periods/close", [
            'year' => (int) now()->format('Y'),
            'month' => (int) now()->format('n'),
        ])->assertUnprocessable()->assertJsonValidationErrors('periode');
    }

    public function test_tutup_dan_buka(): void
    {
        [$user, $company, $branch, $catId] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";
        $today = now()->toDateString();
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');

        // tolak draft agar bisa tutup
        $draftId = $this->draftExpense($api(), $company->id, $branch->id, $catId, $today);
        $api()->postJson("{$base}/expenses/{$draftId}/reject")->assertOk();

        $periodId = $api()->postJson("{$base}/accounting-periods/close", [
            'year' => $year, 'month' => $month,
        ])->assertOk()->assertJsonPath('data.status', 'tutup')->json('data.id');

        $api()->getJson("{$base}/accounting-periods?status=tutup")->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $periodId)
            ->assertJsonPath('data.0.status', 'tutup');
        $api()->getJson("{$base}/accounting-periods?status=buka")->assertOk()->assertJsonPath('meta.total', 0);

        // tutup ganda: tolak
        $api()->postJson("{$base}/accounting-periods/close", [
            'year' => $year, 'month' => $month,
        ])->assertUnprocessable();

        // posting ke bulan tutup: tolak
        $id = $this->draftExpense($api(), $company->id, $branch->id, $catId, $today);
        $api()->postJson("{$base}/expenses/{$id}/approve")->assertUnprocessable()
            ->assertJsonValidationErrors('periode');

        // bulan depan: tolak
        $next = now()->addMonth();
        $api()->postJson("{$base}/accounting-periods/close", [
            'year' => (int) $next->format('Y'), 'month' => (int) $next->format('n'),
        ])->assertUnprocessable();

        // buka lagi: posting jalan
        $api()->postJson("{$base}/accounting-periods/{$periodId}/reopen")
            ->assertOk()->assertJsonPath('data.status', 'buka');
        $api()->postJson("{$base}/expenses/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        // tanpa izin: tolak
        $karyawan = User::factory()->create();
        $this->actingAs($karyawan, 'sanctum')->postJson("{$base}/accounting-periods/close", [
            'year' => $year, 'month' => $month,
        ])->assertForbidden();
    }
}
