<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'GUD', 'name' => 'Gudang']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'PT Sumber']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        return [$user, $company, $branch, $supplier, $item];
    }

    public function test_alur_penuh_sebagian_lalu_lunas(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/purchase-orders";

        $id = $api()->postJson($base, [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 10, 'unit_price' => 48000]],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $api()->postJson("{$base}/{$id}/approve")->assertUnprocessable();
        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/approve")->assertOk();
        $api()->postJson("{$base}/{$id}/order")->assertOk()->assertJsonPath('data.status', 'dipesan');

        // terima sebagian 4 dari 10
        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 4, 'unit_cost' => 48000]],
            'due_at' => '2026-11-10',
        ])->assertOk()->assertJsonPath('data.status', 'dipesan');

        $detail = $api()->getJson("{$base}/{$id}")->assertOk()->json('data');
        $this->assertEquals('4.000', $detail['items'][0]['qtyReceived']);
        $this->assertEquals('192000.00', $detail['bill']['amount']);
        $this->assertEquals('2026-11-10', $detail['bill']['dueAt']);

        $stok = $api()->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->assertOk()->json('data');
        $this->assertEquals('4.000', $stok[0]['qty']);
        $this->assertEquals('48000.00', $stok[0]['avgCost']);

        // terima sisa 6 → selesai
        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 6, 'unit_cost' => 50000]],
        ])->assertOk()->assertJsonPath('data.status', 'selesai');

        $api()->getJson("{$base}/{$id}/receipts")->assertOk()->assertJsonPath('meta.total', 2);

        // terima lagi setelah selesai: tolak
        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 1, 'unit_cost' => 50000]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');
    }

    public function test_terima_melebihi_sisa_ditolak_dan_batal(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/purchase-orders";

        $id = $api()->postJson($base, [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 10, 'unit_price' => 48000]],
        ])->assertCreated()->json('data.id');

        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/approve")->assertOk();
        $api()->postJson("{$base}/{$id}/order")->assertOk();

        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 11, 'unit_cost' => 48000]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        // batal dari dipesan: boleh; batal dari selesai: tidak
        $api()->postJson("{$base}/{$id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'dibatalkan');
    }

    public function test_scope_dan_guest(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $base = "/api/v1/companies/{$company->id}/purchase-orders";

        $this->postJson($base, [])->assertUnauthorized();

        $api = fn () => $this->actingAs($user, 'sanctum');
        $api()->postJson($base, [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id + 9999, 'qty_ordered' => 1, 'unit_price' => 1000]],
        ])->assertNotFound();

        $otherSupplier = Supplier::create(['company_id' => $other->id, 'name' => 'Luar']);
        $api()->postJson($base, [
            'branch_id' => $branch->id,
            'supplier_id' => $otherSupplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 1, 'unit_price' => 1000]],
        ])->assertNotFound()->assertJsonPath('status', 'error');
    }
}
