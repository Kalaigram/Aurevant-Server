<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidReturnTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $oli = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $oli->id, 'branch_id' => $branch->id, 'price' => 50000]);

        return [$user, $company, $branch, $oli];
    }

    private function openShift(User $user, Company $company, Branch $branch): int
    {
        return $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 0]
        )->json('data.id');
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => 40000]
        )->assertCreated();
    }

    private function sell(User $user, Company $company, Branch $branch, Item $item, float $qty): array
    {
        return $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales",
            [
                'items' => [['item_id' => $item->id, 'qty' => $qty]],
                'payments' => [['method' => 'tunai', 'amount' => $qty * 50000]],
            ]
        )->assertCreated()->json('data');
    }

    private function balance(User $user, Company $company, Branch $branch): ?string
    {
        $data = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->json('data');

        return $data[0]['qty'] ?? null;
    }

    public function test_void_mengembalikan_stok(): void
    {
        [$user, $company, $branch, $oli] = $this->setupWorld();
        $shiftId = $this->openShift($user, $company, $branch);
        $this->stockIn($user, $company, $branch, $oli, 100);
        $nota = $this->sell($user, $company, $branch, $oli, 10);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}";

        $this->assertEquals('90.000', $this->balance($user, $company, $branch));

        $api()->postJson("{$base}/sales/{$nota['id']}/void", ['reason' => 'Salah input'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'void')
            ->assertJsonPath('data.refundAmount', '500000.00');

        $this->assertEquals('100.000', $this->balance($user, $company, $branch));
        $this->assertEquals('void', Sale::find($nota['id'])->status);

        // void dua kali: tolak
        $api()->postJson("{$base}/sales/{$nota['id']}/void", ['reason' => 'Lagi'])
            ->assertUnprocessable()->assertJsonPath('status', 'error');

        // tutup shift dulu → void nota baru harus ditolak
        $nota2 = $this->sell($user, $company, $branch, $oli, 5);
        $api()->postJson("{$base}/shifts/{$shiftId}/close", ['closing_cash_counted' => 0])->assertOk();
        $api()->postJson("{$base}/sales/{$nota2['id']}/void", ['reason' => 'Telat'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.sale.0', 'Shift sudah ditutup, gunakan retur.');
    }

    public function test_retur_sebagian_layak_dan_rusak(): void
    {
        [$user, $company, $branch, $oli] = $this->setupWorld();
        $this->openShift($user, $company, $branch);
        $this->stockIn($user, $company, $branch, $oli, 100);
        $nota = $this->sell($user, $company, $branch, $oli, 10);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}";
        $saleItemId = SaleItem::where('sale_id', $nota['id'])->firstOrFail()->id;

        // retur 3 layak → stok +3, refund 3x50000
        $api()->postJson("{$base}/sales/{$nota['id']}/returns", [
            'reason' => 'Pelanggan berubah pikiran',
            'refund_method' => 'tunai',
            'items' => [['sale_item_id' => $saleItemId, 'qty' => 3, 'condition' => 'layak']],
        ])->assertCreated()->assertJsonPath('data.refundAmount', '150000.00');

        $this->assertEquals('93.000', $this->balance($user, $company, $branch));
        $this->assertEquals('lunas', Sale::find($nota['id'])->status);

        // retur 2 rusak → stok tetap, refund tetap jalan
        $api()->postJson("{$base}/sales/{$nota['id']}/returns", [
            'reason' => 'Cacat',
            'refund_method' => 'tunai',
            'items' => [['sale_item_id' => $saleItemId, 'qty' => 2, 'condition' => 'rusak']],
        ])->assertCreated()->assertJsonPath('data.refundAmount', '100000.00');

        $this->assertEquals('93.000', $this->balance($user, $company, $branch));

        // retur melebihi sisa (10-3-2=5): tolak
        $api()->postJson("{$base}/sales/{$nota['id']}/returns", [
            'reason' => 'Serakah',
            'refund_method' => 'tunai',
            'items' => [['sale_item_id' => $saleItemId, 'qty' => 6, 'condition' => 'layak']],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        // retur sisa 5 → nota jadi retur penuh
        $api()->postJson("{$base}/sales/{$nota['id']}/returns", [
            'reason' => 'Sisa',
            'refund_method' => 'tunai',
            'items' => [['sale_item_id' => $saleItemId, 'qty' => 5, 'condition' => 'layak']],
        ])->assertCreated();
        $this->assertEquals('retur', Sale::find($nota['id'])->status);
        $this->assertEquals('98.000', $this->balance($user, $company, $branch));

        // daftar retur cabang memuat 3 dokumen
        $api()->getJson("{$base}/returns")->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_guest_dan_scope_ditolak(): void
    {
        [$user, $company, $branch, $oli] = $this->setupWorld();
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);

        $this->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/1/void", ['reason' => 'x'])
            ->assertUnauthorized();

        $api = fn () => $this->actingAs($user, 'sanctum');
        $api()->postJson("/api/v1/companies/{$other->id}/branches/{$branch->id}/sales/1/void", ['reason' => 'x'])
            ->assertNotFound();
    }
}
