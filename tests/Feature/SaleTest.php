<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemConsumable;
use App\Models\ItemPrice;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'tko', 'name' => 'Toko']);

        $oli = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $kain = Item::create([
            'company_id' => $company->id, 'code' => 'KAIN-01', 'name' => 'Kain Lap',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $servis = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-01', 'name' => 'Servis Ringan',
            'type' => 'jasa', 'duration_minutes' => 60, 'track_stock' => false,
        ]);
        ItemConsumable::create(['item_id' => $servis->id, 'material_item_id' => $kain->id, 'qty' => 2]);

        return [$user, $company, $branch, $oli, $kain, $servis];
    }

    private function setPrices(Branch $branch, Item $oli, Item $kain, Item $servis): void
    {
        foreach ([$oli, $kain, $servis] as $index => $item) {
            ItemPrice::create([
                'item_id' => $item->id,
                'branch_id' => $branch->id,
                'price' => [50000, 5000, 75000][$index],
            ]);
        }
    }

    private function openShift(User $user, Company $company, Branch $branch): int
    {
        return $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 100000]
        )->assertCreated()->json('data.id');
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty, float $cost): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => $cost]
        )->assertCreated();
    }

    private function balance(User $user, Company $company, Branch $branch, int $itemId): ?string
    {
        $data = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->json('data');

        foreach ($data as $row) {
            if ($row['itemId'] === $itemId) {
                return $row['qty'];
            }
        }

        return null;
    }

    public function test_nota_campur_hitung_benar_dan_potong_stok(): void
    {
        [$user, $company, $branch, $oli, $kain, $servis] = $this->setupWorld();
        $this->openShift($user, $company, $branch);
        $this->setPrices($branch, $oli, $kain, $servis);
        $this->stockIn($user, $company, $branch, $oli, 100, 40000);
        $this->stockIn($user, $company, $branch, $kain, 10, 5000);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales";

        $nota = $api()->postJson($url, [
            'idempotency_key' => 'kasir-1',
            'items' => [
                ['item_id' => $oli->id, 'qty' => 2],
                ['item_id' => $servis->id, 'qty' => 1, 'discount_percent' => 10, 'tax_percent' => 10],
            ],
            'payments' => [
                ['method' => 'tunai', 'amount' => 100000, 'tendered' => 100000],
                ['method' => 'qris', 'amount' => 74250, 'reference' => 'QR123'],
            ],
        ])->assertCreated()->assertJsonPath('data.status', 'lunas')->json('data');

        // 2x50000 + (75000-10%+10%) = 100000 + 74250
        $this->assertEquals('175000.00', $nota['subtotal']);
        $this->assertEquals('7500.00', $nota['discountTotal']);
        $this->assertEquals('6750.00', $nota['taxTotal']);
        $this->assertEquals('174250.00', $nota['grandTotal']);
        $this->assertMatchesRegularExpression('/^TKO-\d{6}-\d{4}$/', $nota['number']);

        // stok: oli 100-2, kain 10-2 (bahan servis), hpp oli 2x40000
        $this->assertEquals('98.000', $this->balance($user, $company, $branch, $oli->id));
        $this->assertEquals('8.000', $this->balance($user, $company, $branch, $kain->id));
        $this->assertEquals('80000.00', $nota['items'][0]['hppAmount']);

        // idempotency: kunci sama → nota sama, tidak ganda
        $ulang = $api()->postJson($url, [
            'idempotency_key' => 'kasir-1',
            'items' => [['item_id' => $oli->id, 'qty' => 99]],
            'payments' => [['method' => 'tunai', 'amount' => 1]],
        ])->assertOk()->assertJsonPath('status', 'success')->json('data');
        $this->assertEquals($nota['number'], $ulang['number']);
        $this->assertEquals(1, Sale::count());
    }

    public function test_validasi_nota_ketat(): void
    {
        [$user, $company, $branch, $oli] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales";

        $this->postJson($url, [])->assertUnauthorized();

        // tanpa shift terbuka
        $api()->postJson($url, [
            'items' => [['item_id' => $oli->id, 'qty' => 1]],
            'payments' => [['method' => 'tunai', 'amount' => 1]],
        ])->assertUnprocessable()->assertJsonPath('errors.shift.0', 'Belum ada shift terbuka di cabang ini.');

        $this->openShift($user, $company, $branch);

        // tanpa harga cabang
        $api()->postJson($url, [
            'items' => [['item_id' => $oli->id, 'qty' => 1]],
            'payments' => [['method' => 'tunai', 'amount' => 1]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        ItemPrice::create(['item_id' => $oli->id, 'branch_id' => $branch->id, 'price' => 50000]);
        $this->stockIn($user, $company, $branch, $oli, 5, 40000);

        // bayar kurang
        $api()->postJson($url, [
            'items' => [['item_id' => $oli->id, 'qty' => 1]],
            'payments' => [['method' => 'tunai', 'amount' => 1000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('payments');

        // diskon ganda + non-tunai tanpa referensi
        $api()->postJson($url, [
            'items' => [['item_id' => $oli->id, 'qty' => 1, 'discount_percent' => 5, 'discount_amount' => 100]],
            'payments' => [['method' => 'qris', 'amount' => 50000]],
        ])->assertUnprocessable();

        // stok kurang
        $api()->postJson($url, [
            'items' => [['item_id' => $oli->id, 'qty' => 99]],
            'payments' => [['method' => 'tunai', 'amount' => 99 * 50000]],
        ])->assertUnprocessable()->assertJsonPath('message', 'Stok tidak mencukupi.');
    }
}
