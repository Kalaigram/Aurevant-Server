<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Pusat']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        return [$user, $company, $branch, $item];
    }

    public function test_guest_ditolak(): void
    {
        [, $company, $branch] = $this->setupWorld();

        $this->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->assertUnauthorized();
    }

    public function test_pembelian_penjualan_dan_hpp(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements";

        $api()->postJson($url, [
            'item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 50000,
        ])->assertCreated()
            ->assertJsonPath('data.balanceAfter', '100.000')
            ->assertJsonPath('data.itemCode', 'OLI-01')
            ->assertJsonPath('data.itemName', 'Oli')
            ->assertJsonPath('data.unit', 'pcs');

        $api()->postJson($url, [
            'item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 70000,
        ])->assertCreated();

        $api()->postJson($url, [
            'item_id' => $item->id, 'type' => 'penjualan', 'qty' => 50,
        ])->assertCreated()->assertJsonPath('data.hppAmount', '3000000.00');

        $saldo = $api()->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->assertOk();
        $this->assertEquals('150.000', $saldo->json('data.0.qty'));
        $this->assertEquals('60000.00', $saldo->json('data.0.avgCost'));

        // pencarian saldo dan riwayat memuat nama item
        $api()->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock?q=OLI")
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.itemName', 'Oli')
            ->assertJsonPath('data.0.unit', 'pcs');
        $api()->getJson("{$url}?q=OLI")
            ->assertOk()->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.itemCode', 'OLI-01')
            ->assertJsonPath('data.0.itemName', 'Oli')
            ->assertJsonPath('data.0.unit', 'pcs');
        $api()->getJson("{$url}?q=tidak-ada")
            ->assertOk()->assertJsonPath('meta.total', 0);

        // jual melebihi saldo: tolak
        $api()->postJson($url, [
            'item_id' => $item->id, 'type' => 'penjualan', 'qty' => 151,
        ])->assertUnprocessable()->assertJson([
            'status' => 'error',
            'message' => 'Stok tidak mencukupi.',
        ]);

        // pembelian tanpa harga beli: tolak
        $api()->postJson($url, [
            'item_id' => $item->id, 'type' => 'pembelian', 'qty' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_cost');
    }

    public function test_penyesuaian_dan_scope_lintas_company(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherBranch = Branch::create(['company_id' => $other->id, 'code' => 'HQ', 'name' => 'Pusat']);
        $otherItem = Item::create([
            'company_id' => $other->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $api()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements", [
            'item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 50000,
        ])->assertCreated();

        $api()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements", [
            'item_id' => $item->id, 'type' => 'penyesuaian', 'qty' => -5, 'note' => 'Rusak',
        ])->assertCreated()->assertJsonPath('data.balanceAfter', '95.000');

        // item perusahaan lain: 404
        $api()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements", [
            'item_id' => $otherItem->id, 'type' => 'penjualan', 'qty' => 1,
        ])->assertNotFound()->assertJsonPath('status', 'error');

        // cabang perusahaan lain: 404
        $api()->getJson("/api/v1/companies/{$company->id}/branches/{$otherBranch->id}/stock")
            ->assertNotFound()->assertJsonPath('status', 'error');

        // riwayat bisa difilter per item dan dicari lewat catatan
        $api()->getJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements?item_id={$item->id}"
        )->assertOk()->assertJsonPath('meta.total', 2);
        $api()->getJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements?q=Rusak"
        )->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.note', 'Rusak');
    }

    public function test_stok_minimum_filter_dan_alert(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}";
        $minimumUrl = "{$base}/stock/{$item->id}/minimum";

        $api()->postJson("{$base}/stock/movements", [
            'item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 50000,
        ])->assertCreated();

        // staf tanpa stok.opname tidak boleh mengubah batas minimum
        $staff = User::factory()->create();
        $this->member($staff, $company, $branch->id);
        $this->actingAs($staff, 'sanctum')
            ->putJson($minimumUrl, ['min_stock' => 10])
            ->assertForbidden();

        // nilai negatif ditolak
        $api()->putJson($minimumUrl, ['min_stock' => -1])
            ->assertUnprocessable()->assertJsonValidationErrors('min_stock');

        $api()->putJson($minimumUrl, ['min_stock' => 110])
            ->assertOk()
            ->assertJsonPath('data.minStock', '110.000')
            ->assertJsonPath('data.lowStock', true);

        // batas minimum belum memicu notifikasi
        $this->assertSame(0, AppNotification::query()->where('type', 'stok.menipis')->count());

        $api()->getJson("{$base}/stock?low_stock=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.itemCode', 'OLI-01');

        $dashboard = $api()->getJson("/api/v1/companies/{$company->id}/dashboard")
            ->assertOk()
            ->json('data.operasional');
        $this->assertSame(1, $dashboard['stok_menipis']);
        $this->assertSame('OLI-01', $dashboard['stok_menipis_items'][0]['item_code']);
        $this->assertSame('Pusat', $dashboard['stok_menipis_items'][0]['branch_name']);

        // naikkan stok di atas minimum, lalu turunkan hingga melewati batas
        $api()->putJson($minimumUrl, ['min_stock' => 90])
            ->assertOk()->assertJsonPath('data.lowStock', false);
        $this->assertSame(0, AppNotification::query()->where('type', 'stok.menipis')->count());

        $api()->postJson("{$base}/stock/movements", [
            'item_id' => $item->id, 'type' => 'penjualan', 'qty' => 20,
        ])->assertCreated();

        $this->assertSame(1, AppNotification::query()->where('type', 'stok.menipis')->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'stok.menipis',
            'title' => 'Stok menipis: Oli',
            'link' => '/stock',
        ]);

        // masih di bawah minimum: tidak mengirim peringatan berulang
        $api()->postJson("{$base}/stock/movements", [
            'item_id' => $item->id, 'type' => 'penjualan', 'qty' => 5,
        ])->assertCreated();
        $this->assertSame(1, AppNotification::query()->where('type', 'stok.menipis')->count());

        // kosongkan batas minimum
        $api()->putJson($minimumUrl, ['min_stock' => null])
            ->assertOk()
            ->assertJsonPath('data.minStock', null)
            ->assertJsonPath('data.lowStock', false);
        $api()->getJson("{$base}/stock?low_stock=1")
            ->assertOk()->assertJsonPath('meta.total', 0);

        // item perusahaan lain tidak dapat diatur
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherItem = Item::create([
            'company_id' => $other->id, 'code' => 'GULA-01', 'name' => 'Gula',
            'type' => 'barang', 'unit' => 'kg',
        ]);
        $api()->putJson("{$base}/stock/{$otherItem->id}/minimum", ['min_stock' => 5])
            ->assertNotFound();
    }
}
