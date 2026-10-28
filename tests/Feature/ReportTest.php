<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $a = Branch::create(['company_id' => $company->id, 'code' => 'A', 'name' => 'Cabang A']);
        $b = Branch::create(['company_id' => $company->id, 'code' => 'B', 'name' => 'Cabang B']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $a->id, 'price' => 50000]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $b->id, 'price' => 55000]);

        return [$user, $company, $a, $b, $item];
    }

    private function api(User $user)
    {
        return $this->actingAs($user, 'sanctum');
    }

    private function openShift(User $user, Company $company, Branch $branch): void
    {
        $this->api($user)->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 0]
        )->assertCreated();
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty): void
    {
        $this->api($user)->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => 40000]
        )->assertCreated();
    }

    private function sell(User $user, Company $company, Branch $branch, Item $item, float $qty): array
    {
        $price = $branch->code === 'A' ? 50000 : 55000;

        return $this->api($user)->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales",
            [
                'items' => [['item_id' => $item->id, 'qty' => $qty]],
                'payments' => [['method' => 'tunai', 'amount' => $qty * $price]],
            ]
        )->assertCreated()->json('data');
    }

    public function test_harian_dan_konsolidasi_otomatis_terisi(): void
    {
        [$user, $company, $a, $b, $item] = $this->setupWorld();
        $today = now()->toDateString();
        $api = fn () => $this->api($user);

        $this->openShift($user, $company, $a);
        $this->openShift($user, $company, $b);
        $this->stockIn($user, $company, $a, $item, 100);
        $this->stockIn($user, $company, $b, $item, 100);

        $notaA = $this->sell($user, $company, $a, $item, 2);
        $this->sell($user, $company, $b, $item, 1);

        // harian cabang A otomatis terisi saat nota terbit
        $harian = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/daily?from={$today}&to={$today}&branch_id={$a->id}"
        )->assertOk()->json();
        $this->assertEquals('100000.00', $harian['data']['rows'][0]['omzet']);
        $this->assertEquals(1, $harian['data']['rows'][0]['transactions']);
        $this->assertEquals('80000.00', $harian['data']['rows'][0]['hppTotal']);
        $this->assertEquals('100000.00', $harian['data']['rows'][0]['cashTunai']);

        // konsolidasi: A 100rb + B 55rb
        $konsolidasi = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/consolidation?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals(155000.0, $konsolidasi['grand']['omzet']);
        $this->assertEquals(2, $konsolidasi['grand']['transactions']);

        // void nota A → ringkasan ikut nol otomatis
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$a->id}/sales/{$notaA['id']}/void",
            ['reason' => 'Salah']
        )->assertOk();
        $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/daily?from={$today}&to={$today}&branch_id={$a->id}"
        )->assertOk()->assertJsonPath('data.rows.0.omzet', '0.00');
    }

    public function test_top_produk_dan_perbandingan_cabang(): void
    {
        [$user, $company, $a, $b, $item] = $this->setupWorld();
        $today = now()->toDateString();
        $api = fn () => $this->api($user);

        $this->openShift($user, $company, $a);
        $this->openShift($user, $company, $b);
        $this->stockIn($user, $company, $a, $item, 100);
        $this->stockIn($user, $company, $b, $item, 100);

        $this->sell($user, $company, $a, $item, 2);
        $this->sell($user, $company, $b, $item, 1);

        $top = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/top-products?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertSame('OLI-01', $top['rows'][0]['code']);
        $this->assertEquals(3.0, $top['rows'][0]['qty']);
        $this->assertEquals(155000.0, $top['rows'][0]['revenue']);
        $this->assertEquals(120000.0, $top['rows'][0]['hpp']);
        $this->assertEquals(35000.0, $top['rows'][0]['margin']);
        $this->assertEquals(2, $top['rows'][0]['transactions']);

        $topA = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/top-products?from={$today}&to={$today}&branch_id={$a->id}"
        )->assertOk()->json('data');
        $this->assertEquals(2.0, $topA['rows'][0]['qty']);
        $this->assertEquals(100000.0, $topA['rows'][0]['revenue']);
        $this->assertEquals(1, $topA['rows'][0]['transactions']);

        $konsolidasi = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/consolidation?from={$today}&to={$today}"
        )->assertOk()->json('data');
        $this->assertEquals(77500.0, $konsolidasi['grand']['avgTicket']);
        $this->assertEquals(35000.0, $konsolidasi['grand']['margin']);
        $this->assertEquals(100000.0, $konsolidasi['branches'][0]['avgTicket']);
        $this->assertEquals(55000.0, $konsolidasi['branches'][1]['avgTicket']);

        $karyawan = User::factory()->create();
        $this->actingAs($karyawan, 'sanctum')->getJson(
            "/api/v1/companies/{$company->id}/reports/top-products?from={$today}&to={$today}"
        )->assertForbidden();
    }

    public function test_retur_masuk_refund_dan_refresh_manual(): void
    {
        [$user, $company, $a, $b, $item] = $this->setupWorld();
        $today = now()->toDateString();
        $api = fn () => $this->api($user);

        $this->openShift($user, $company, $b);
        $this->stockIn($user, $company, $b, $item, 100);
        $nota = $this->sell($user, $company, $b, $item, 4);

        $saleItemId = SaleItem::where('sale_id', $nota['id'])->firstOrFail()->id;
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$b->id}/sales/{$nota['id']}/returns",
            [
                'reason' => 'Cacat',
                'refund_method' => 'tunai',
                'items' => [['sale_item_id' => $saleItemId, 'qty' => 1, 'condition' => 'rusak']],
            ]
        )->assertCreated();

        // omzet tetap (nota retur sebagian), refund tercatat, net = omzet - refund
        $harian = $api()->getJson(
            "/api/v1/companies/{$company->id}/reports/daily?from={$today}&to={$today}&branch_id={$b->id}"
        )->assertOk()->json('data');
        $this->assertEquals('220000.00', $harian['rows'][0]['omzet']);
        $this->assertEquals('55000.00', $harian['rows'][0]['refundTotal']);
        $this->assertEquals('165000.00', $harian['rows'][0]['netOmzet']);

        // refresh manual jalan
        $api()->postJson("/api/v1/companies/{$company->id}/reports/refresh", ['date' => $today])
            ->assertOk()->assertJsonPath('status', 'success');

        // tanpa izin: 403
        $karyawan = User::factory()->create();
        $this->actingAs($karyawan, 'sanctum')->getJson(
            "/api/v1/companies/{$company->id}/reports/daily?from={$today}&to={$today}"
        )->assertForbidden();
    }
}
