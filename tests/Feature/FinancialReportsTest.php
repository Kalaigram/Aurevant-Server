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

class FinancialReportsTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_arus_kas_neraca_dan_audit_keuangan(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $catId = app(ExpenseRepositoryInterface::class)
            ->createCategory($company, ['name' => 'Operasional'])->id;

        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";
        $today = now()->toDateString();

        // biaya 50rb disetujui (kas keluar)
        $expId = $api()->postJson("{$base}/expenses", [
            'branch_id' => $branch->id, 'expense_category_id' => $catId,
            'amount' => 50000, 'date' => $today, 'description' => 'ATK',
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/expenses/{$expId}/approve")->assertOk();

        // piutang 200rb, terima 70rb (kas masuk)
        $recId = $api()->postJson("{$base}/receivables", [
            'branch_id' => $branch->id, 'amount' => 200000, 'note' => 'Bon',
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/receivables/{$recId}/pay", ['amount' => 70000])->assertOk();

        $flow = $api()->getJson("{$base}/reports/cash-flow?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $this->assertEquals(70000.0, $flow['kas_masuk']);
        $this->assertEquals(50000.0, $flow['kas_keluar']);
        $this->assertEquals(20000.0, $flow['kas_bersih']);

        $sheet = $api()->getJson("{$base}/reports/balance-sheet?as_of={$today}")
            ->assertOk()->json('data');
        $this->assertEquals(20000.0, $sheet['aktiva']['kas']);
        $this->assertEquals(130000.0, $sheet['aktiva']['piutang']);
        $this->assertEquals(150000.0, $sheet['aktiva']['total']);
        $this->assertEquals(0.0, $sheet['kewajiban']['total']);
        $this->assertEquals(150000.0, $sheet['modal']);
        $this->assertEquals(
            $sheet['aktiva']['total'],
            $sheet['kewajiban']['total'] + $sheet['modal']
        );

        // audit keuangan tercatat
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'action' => 'biaya.setujui',
            'entity_type' => 'expense', 'entity_id' => $expId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'action' => 'piutang.catat',
            'entity_type' => 'receivable', 'entity_id' => $recId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'action' => 'piutang.terima',
            'entity_type' => 'receivable', 'entity_id' => $recId,
        ]);
    }
}
