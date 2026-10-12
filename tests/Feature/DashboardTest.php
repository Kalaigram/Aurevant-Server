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

class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_dashboard_per_role(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $catId = app(ExpenseRepositoryInterface::class)
            ->createCategory($company, ['name' => 'Operasional'])->id;
        $base = "/api/v1/companies/{$company->id}";

        $this->getJson("{$base}/dashboard")->assertUnauthorized();

        // user tanpa izin: hanya info dasar
        $plain = User::factory()->create();
        $minimal = $this->actingAs($plain, 'sanctum')->getJson("{$base}/dashboard")->assertOk()->json('data');
        $this->assertArrayHasKey('company', $minimal);
        $this->assertArrayHasKey('subscription', $minimal);
        $this->assertArrayNotHasKey('keuangan', $minimal);
        $this->assertArrayNotHasKey('penjualan', $minimal);

        // pemilik: semua seksi
        $api = fn () => $this->actingAs($owner, 'sanctum');
        $full = $api()->getJson("{$base}/dashboard")->assertOk()->json('data');
        foreach (['keuangan', 'penjualan', 'operasional', 'sdm', 'penggajian', 'karyawan'] as $section) {
            $this->assertArrayHasKey($section, $full);
        }
        $this->assertSame('PT A', $full['company']['name']);

        // angka keuangan mengikuti transaksi
        $expId = $api()->postJson("{$base}/expenses", [
            'branch_id' => $branch->id, 'expense_category_id' => $catId,
            'amount' => 50000, 'date' => now()->toDateString(), 'description' => 'ATK',
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/expenses/{$expId}/approve")->assertOk();

        $keuangan = $api()->getJson("{$base}/dashboard")->assertOk()->json('data.keuangan');
        $this->assertEquals(-50000.0, $keuangan['laba_bersih_bulan_ini']);
        $this->assertEquals(-50000.0, $keuangan['kas_bersih_bulan_ini']);
    }
}
