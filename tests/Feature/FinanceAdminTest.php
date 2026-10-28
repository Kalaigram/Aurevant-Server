<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class FinanceAdminTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: Company, 2: Branch, 3: ExpenseCategory} */
    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Listrik']);

        return [$owner, $company, $branch, $category];
    }

    public function test_bagan_akun_hierarki_dan_pengamanan(): void
    {
        [$owner, $company] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $parentId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/chart-of-accounts", [
            'code' => '1-0000', 'name' => 'Aset', 'type' => 'aset',
        ])->assertCreated()->json('data.id');

        $childId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/chart-of-accounts", [
            'code' => '1-1000', 'name' => 'Kas', 'type' => 'aset', 'parent_id' => $parentId,
        ])->assertCreated()->assertJsonPath('data.parentId', $parentId)->json('data.id');

        $this->actingAs($owner, 'sanctum')->getJson("{$base}/chart-of-accounts?type=aset")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($owner, 'sanctum')->putJson("{$base}/chart-of-accounts/{$parentId}", [
            'code' => '1-0000', 'name' => 'Aset', 'type' => 'aset', 'parent_id' => $childId,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("{$base}/chart-of-accounts/{$parentId}")
            ->assertUnprocessable();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("{$base}/chart-of-accounts/{$childId}")
            ->assertOk();

        $other = User::factory()->create();
        $this->member($other, $company);
        $this->actingAs($other, 'sanctum')->getJson("{$base}/chart-of-accounts")
            ->assertForbidden();
    }

    public function test_anggaran_realisasi_dan_biaya_berulang(): void
    {
        [$owner, $company, $branch, $category] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";
        $year = (int) now()->year;
        $month = (int) now()->month;

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/budgets", [
            'branch_id' => $branch->id,
            'year' => $year,
            'month' => $month,
            'expense_category_id' => $category->id,
            'amount' => 1000000,
            'note' => 'Anggaran listrik',
        ])->assertCreated();

        $expenseId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/expenses", [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'amount' => 200000,
            'date' => now()->toDateString(),
            'description' => 'Listrik bulan ini',
        ])->assertCreated()->json('data.id');
        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/expenses/{$expenseId}/approve")
            ->assertOk();

        $this->actingAs($owner, 'sanctum')->getJson("{$base}/budgets?year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.0.used', '200000.00')
            ->assertJsonPath('data.0.remaining', '800000.00');

        $templateId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/recurring-expenses", [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'amount' => 75000,
            'method' => 'transfer',
            'description' => 'Internet bulanan',
            'frequency' => 'bulanan',
            'next_run_at' => now()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/recurring-expenses/run-due")
            ->assertOk()
            ->assertJsonPath('data.created', 1);
        $this->assertDatabaseHas('expenses', [
            'company_id' => $company->id,
            'recurring_expense_id' => $templateId,
            'status' => 'draft',
        ]);

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/recurring-expenses/run-due")
            ->assertOk()
            ->assertJsonPath('data.created', 0);
    }

    public function test_aset_tetap_disusutkan_sampai_batas(): void
    {
        [$owner, $company, $branch] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $id = $this->actingAs($owner, 'sanctum')->postJson("{$base}/fixed-assets", [
            'branch_id' => $branch->id,
            'name' => 'Mesin kasir',
            'code' => 'AST-01',
            'acquisition_date' => '2026-01-01',
            'acquisition_cost' => 12000000,
            'salvage_value' => 0,
            'useful_life_months' => 12,
        ])->assertCreated()
            ->assertJsonPath('data.monthlyDepreciation', '1000000.00')
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/fixed-assets/{$id}/depreciate", ['months' => 2])
            ->assertOk()
            ->assertJsonPath('data.accumulatedDepreciation', '2000000.00')
            ->assertJsonPath('data.bookValue', '10000000.00');

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/fixed-assets/{$id}/depreciate", ['months' => 120])
            ->assertOk()
            ->assertJsonPath('data.accumulatedDepreciation', '12000000.00')
            ->assertJsonPath('data.bookValue', '0.00');

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/fixed-assets/{$id}/depreciate")
            ->assertUnprocessable();
    }

    public function test_rekonsiliasi_bank_memakai_saldo_buku_dan_mengunci(): void
    {
        [$owner, $company, $branch] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";

        $accountId = $this->actingAs($owner, 'sanctum')->postJson("{$base}/chart-of-accounts", [
            'code' => '1-1100', 'name' => 'Bank', 'type' => 'aset',
        ])->assertCreated()->json('data.id');

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'date' => now()->startOfMonth()->toDateString(),
            'memo' => 'Setoran awal',
            'source_type' => 'manual-test',
            'source_id' => 1,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $accountId,
            'debit' => 150000,
            'credit' => 0,
        ]);

        $period = now()->format('Y-m');
        $id = $this->actingAs($owner, 'sanctum')->postJson("{$base}/bank-reconciliations", [
            'account_id' => $accountId,
            'period' => $period,
            'statement_balance' => 150000,
        ])->assertCreated()
            ->assertJsonPath('data.bookBalance', '150000.00')
            ->assertJsonPath('data.difference', '0.00')
            ->assertJsonPath('data.isBalanced', true)
            ->json('data.id');

        $this->actingAs($owner, 'sanctum')->postJson("{$base}/bank-reconciliations", [
            'account_id' => $accountId,
            'period' => $period,
            'statement_balance' => 150000,
        ])->assertUnprocessable();

        $this->actingAs($owner, 'sanctum')
            ->putJson("{$base}/bank-reconciliations/{$id}", ['statement_balance' => 140000])
            ->assertOk()
            ->assertJsonPath('data.difference', '-10000.00')
            ->assertJsonPath('data.isBalanced', false);

        $this->actingAs($owner, 'sanctum')
            ->postJson("{$base}/bank-reconciliations/{$id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $this->actingAs($owner, 'sanctum')
            ->putJson("{$base}/bank-reconciliations/{$id}", ['statement_balance' => 0])
            ->assertUnprocessable();
    }
}
