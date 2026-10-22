<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemConsumable;
use App\Models\ItemPrice;
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

        // bayar sisa 80000 tunai, 20000 dari DP
        $nota = $api()->postJson("{$base}/{$id}/pay", [
            'payments' => [['method' => 'tunai', 'amount' => 80000]],
        ])->assertCreated()->json('data');

        $this->assertEquals('100000.00', $nota['grandTotal']);
        $methods = collect($nota['payments'])->pluck('method')->all();
        $this->assertEquals(['dp', 'tunai'], $methods);

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
}
