<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemConsumable;
use App\Models\ItemPrice;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $teknisi = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'BKL', 'name' => 'Bengkel']);
        $customer = Customer::create(['company_id' => $company->id, 'name' => 'Pak Dudi', 'phone' => '08100']);
        $servis = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-01', 'name' => 'Servis Ringan',
            'type' => 'jasa', 'track_stock' => false,
            'commission_type' => 'persen', 'commission_value' => 10,
        ]);
        $oli = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemConsumable::create(['item_id' => $servis->id, 'material_item_id' => $oli->id, 'qty' => 1]);
        ItemPrice::create(['item_id' => $servis->id, 'branch_id' => $branch->id, 'price' => 100000]);

        return [$user, $teknisi, $company, $branch, $customer, $servis, $oli];
    }

    private function openShift(User $user, Company $company, Branch $branch): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 0]
        )->assertCreated();
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => 40000]
        )->assertCreated();
    }

    public function test_alur_jasa_dp_komisi_sampai_nota(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $this->openShift($user, $company, $branch);
        $this->stockIn($user, $company, $branch, $oli, 10);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $id = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
            'note' => 'Motor beat',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'baru')
            ->assertJsonPath('data.customerName', 'Pak Dudi')
            ->assertJsonPath('data.items.0.itemName', 'Servis Ringan')
            ->json('data.id');

        // barang tidak boleh masuk order jasa
        $api()->postJson($base, [
            'items' => [['item_id' => $oli->id, 'qty' => 1]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        $api()->postJson("{$base}/{$id}/deposit", ['amount' => 20000])
            ->assertOk()->assertJsonPath('data.depositAmount', '20000.00');

        $api()->postJson("{$base}/{$id}/process", ['assignee_id' => $teknisi->id])
            ->assertOk()->assertJsonPath('data.status', 'diproses');

        // bahan belum kepotong sebelum selesai
        $stok = fn () => $api()->getJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock"
        )->json('data.0.qty');
        $this->assertEquals('10.000', $stok());

        $api()->postJson("{$base}/{$id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.items.0.commissionAmount', '10000.00');

        // bahan kepotong 1 saat selesai
        $this->assertEquals('9.000', $stok());

        // harga berubah setelah order dibuat: nota tetap memakai harga snapshot
        ItemPrice::query()->where('item_id', $servis->id)->where('branch_id', $branch->id)
            ->update(['price' => 200000]);

        // bayar sisa 80000 tunai, 20000 dari DP
        $nota = $api()->postJson("{$base}/{$id}/pay", [
            'payments' => [['method' => 'tunai', 'amount' => 80000]],
        ])->assertCreated()->json('data');

        $this->assertEquals('100000.00', $nota['grandTotal']);
        $methods = collect($nota['payments'])->pluck('method')->all();
        $this->assertEquals(['dp', 'tunai'], $methods);

        // bahan tidak terpotong dua kali saat bayar
        $this->assertEquals('9.000', $stok());

        $api()->getJson("{$base}/{$id}")->assertJsonPath('data.status', 'dibayar');
    }

    public function test_daftar_order_jasa_memuat_nama_dan_filter_q(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $id = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        $lain = Customer::create([
            'company_id' => $company->id, 'name' => 'Bu Sari', 'phone' => '08222',
        ]);
        $api()->postJson($base, [
            'customer_id' => $lain->id,
            'items' => [['item_id' => $servis->id, 'qty' => 2]],
        ])->assertCreated();

        $page = $api()->getJson("{$base}?q=Dudi")->assertOk()->json();
        $this->assertSame(1, $page['meta']['total']);
        $this->assertSame($id, $page['data'][0]['id']);
        $this->assertSame('Pak Dudi', $page['data'][0]['customerName']);
        $this->assertSame('Servis Ringan', $page['data'][0]['items'][0]['itemName']);
        $this->assertSame('100000.00', $page['data'][0]['total']);

        $page = $api()->getJson("{$base}?q=Sari")->assertOk()->json();
        $this->assertSame(1, $page['meta']['total']);
        $this->assertSame('Bu Sari', $page['data'][0]['customerName']);
    }

    public function test_agenda_order_jasa_filter_rentang_tanggal(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $awal = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'scheduled_at' => '2026-10-05T09:00:00',
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        $akhir = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'scheduled_at' => '2026-10-06T14:30:00',
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        $api()->postJson($base, [
            'customer_id' => $customer->id,
            'scheduled_at' => '2026-10-10T08:00:00',
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated();

        $api()->postJson($base, [
            'customer_id' => $customer->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated();

        $page = $api()->getJson("{$base}?scheduled_from=2026-10-05&scheduled_to=2026-10-06")
            ->assertOk()->json();

        $this->assertSame(2, $page['meta']['total']);
        $this->assertSame([$awal, $akhir], array_column($page['data'], 'id'));
        $this->assertStringStartsWith('2026-10-05T09:00:00', $page['data'][0]['scheduledAt']);
        $this->assertStringStartsWith('2026-10-06T14:30:00', $page['data'][1]['scheduledAt']);

        $api()->getJson("{$base}?scheduled_from=2026-10-07&scheduled_to=2026-10-06")
            ->assertUnprocessable();
    }

    public function test_estimasi_jasa_pdf_dibatasi_cabang_dan_tenant(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $id = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'scheduled_at' => '2026-10-05T09:00:00',
            'note' => 'Servis rutin',
            'items' => [['item_id' => $servis->id, 'qty' => 2]],
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/{$id}/deposit", ['amount' => 50000])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson("{$base}/{$id}/estimate")->assertUnauthorized();

        $res = $api()->get("{$base}/{$id}/estimate");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString("estimasi-jasa-{$id}.pdf", (string) $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->streamedContent());

        $lain = User::factory()->create();
        $b = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $this->owner($lain, $b);
        $this->actingAs($lain, 'sanctum')->get("{$base}/{$id}/estimate")->assertForbidden();
    }

    public function test_transisi_salah_dp_lebih_dan_guest(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $this->postJson($base, [])->assertUnauthorized();

        $this->openShift($user, $company, $branch);
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $oli->id, 'type' => 'pembelian', 'qty' => 10, 'unit_cost' => 40000]
        )->assertCreated();
        $api = fn () => $this->actingAs($user, 'sanctum');

        $id = $api()->postJson($base, [
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        // selesai langsung dari baru: tolak; bayar sebelum selesai: tolak
        $api()->postJson("{$base}/{$id}/complete")->assertUnprocessable();
        $api()->postJson("{$base}/{$id}/pay", [
            'payments' => [['method' => 'tunai', 'amount' => 100000]],
        ])->assertUnprocessable();

        // DP melebihi total (100000): tolak saat bayar
        $api()->postJson("{$base}/{$id}/deposit", ['amount' => 150000])->assertOk();
        $api()->postJson("{$base}/{$id}/process", [])->assertOk();
        $api()->postJson("{$base}/{$id}/complete")->assertOk();
        $api()->postJson("{$base}/{$id}/pay", [
            'payments' => [['method' => 'tunai', 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        // batal dari selesai: tolak
        $api()->postJson("{$base}/{$id}/cancel")->assertUnprocessable();
    }

    public function test_order_jasa_dari_paket_memakai_harga_paket(): void
    {
        [$user, $teknisi, $company, $branch, $customer, $servis, $oli] = $this->setupWorld();
        $detail = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-02', 'name' => 'Cuci Detailing',
            'type' => 'jasa', 'track_stock' => false,
            'commission_type' => 'nominal', 'commission_value' => 5000,
        ]);
        ItemPrice::create(['item_id' => $detail->id, 'branch_id' => $branch->id, 'price' => 50000]);

        $package = ServicePackage::create([
            'company_id' => $company->id, 'code' => 'PAK-01', 'name' => 'Paket Hemat',
            'price' => 120000, 'status' => 'aktif',
        ]);
        $package->items()->createMany([
            ['item_id' => $servis->id, 'qty' => 1],
            ['item_id' => $detail->id, 'qty' => 1],
        ]);

        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $res = $api()->postJson($base, [
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ])->assertCreated()
            ->assertJsonPath('data.packageId', $package->id)
            ->assertJsonPath('data.packageName', 'Paket Hemat')
            ->assertJsonPath('data.total', '120000.00')
            ->assertJsonPath('data.items.0.itemName', 'Servis Ringan')
            ->assertJsonPath('data.items.0.price', '80000.00')
            ->assertJsonPath('data.items.1.itemName', 'Cuci Detailing')
            ->assertJsonPath('data.items.1.price', '40000.00');

        $this->assertSame(2, count($res->json('data.items')));

        $api()->postJson($base, [
            'package_id' => $package->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        $package->update(['status' => 'nonaktif']);
        $api()->postJson($base, ['package_id' => $package->id])
            ->assertUnprocessable()->assertJsonValidationErrors('package_id');
    }
}
