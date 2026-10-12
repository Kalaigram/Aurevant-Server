<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $branch->id, 'price' => 50000]);

        return [$user, $company, $branch, $item];
    }

    private function openShift(User $user, Company $company, Branch $branch): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 0]
        )->assertCreated();
    }

    public function test_nota_posting_seimbang_dan_void_membalik(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $this->openShift($user, $company, $branch);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 40000]
        )->assertCreated();

        $notaId = $api()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/sales", [
            'items' => [['item_id' => $item->id, 'qty' => 2]],
            'payments' => [['method' => 'tunai', 'amount' => 100000]],
        ])->assertCreated()->json('data.id');

        // jurnal nota: kas D 100rb, pendapatan K 100rb, HPP D 80rb, persediaan K 80rb
        $jurnal = $api()->getJson("/api/v1/companies/{$company->id}/journal-entries?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $this->assertCount(1, $jurnal);
        $debit = collect($jurnal[0]['lines'])->sum('debit');
        $kredit = collect($jurnal[0]['lines'])->sum('credit');
        $this->assertEquals($debit, $kredit);
        $this->assertEquals(180000.0, $debit);

        // laba rugi: pendapatan 100rb, HPP 80rb, bersih 20rb
        $lr = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/profit-loss?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals(100000.0, $lr['pendapatan']);
        $this->assertEquals(80000.0, $lr['hpp']);
        $this->assertEquals(20000.0, $lr['laba_bersih']);

        // neraca saldo seimbang
        $neraca = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/trial-balance?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals($neraca['total_debit'], $neraca['total_credit']);

        // void membalik semua
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/{$notaId}/void",
            ['reason' => 'Salah']
        )->assertOk();
        $lr = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/profit-loss?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals(0.0, $lr['pendapatan']);
        $this->assertEquals(0.0, $lr['laba_bersih']);
        $neraca = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/trial-balance?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals($neraca['total_debit'], $neraca['total_credit']);
    }

    public function test_po_dan_gaji_posting(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $today = now()->toDateString();
        $base = "/api/v1/companies/{$company->id}";

        $supplierId = $api()->postJson("{$base}/suppliers", ['name' => 'PT Sumber'])
            ->assertCreated()->json('data.id');
        $poId = $api()->postJson("{$base}/purchase-orders", [
            'branch_id' => $branch->id, 'supplier_id' => $supplierId,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 10, 'unit_price' => 40000]],
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/purchase-orders/{$poId}/submit")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/approve")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/order")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 10, 'unit_cost' => 40000]],
        ])->assertOk();

        // persediaan D 400rb, hutang K 400rb
        $lr = $api()->getJson("{$base}/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $persediaan = collect($lr['detail'])->firstWhere('code', '1310');
        $hutang = collect($lr['detail'])->firstWhere('code', '2110');
        $this->assertNull($persediaan);
        $this->assertNull($hutang);

        // neraca: persediaan & hutang muncul
        $neraca = $api()->getJson("{$base}/reports/trial-balance?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $codes = collect($neraca['rows'])->pluck('credit', 'code')->all();
        $this->assertEquals(400000.0, $codes['2110']);

        // gaji: komponen + periode + setujui + bayar
        $compId = $api()->postJson("{$base}/salary-components", [
            'name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed',
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/users/{$user->id}/salary-components", [
            'component_id' => $compId, 'amount' => 3000000,
        ])->assertOk();
        $periodId = $api()->postJson("{$base}/payroll-periods", [
            'name' => 'P1', 'date_from' => $today, 'date_to' => $today,
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/payroll-periods/{$periodId}/calculate")->assertOk();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/review")->assertOk();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/approve")->assertOk();
        $api()->postJson("{$base}/payroll-periods/{$periodId}/pay")->assertOk();

        $lr = $api()->getJson("{$base}/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $this->assertEquals(3000000.0, $lr['beban']);
        $this->assertEquals(-3000000.0, $lr['laba_bersih']);
    }
}
