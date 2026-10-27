<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
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
            'company_id' => $company->id,
            'code' => 'OLI-01',
            'name' => 'Oli',
            'type' => 'barang',
            'unit' => 'pcs',
        ]);

        return [$user, $company, $branch, $supplier, $item];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function receiveOrder(User $user, Company $company, Branch $branch, Supplier $supplier, Item $item, float $qty = 10, float $cost = 40000): array
    {
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/purchase-orders";

        $orderId = $api()->postJson($base, [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => $qty, 'unit_price' => $cost]],
        ])->assertCreated()->json('data.id');

        $api()->postJson("{$base}/{$orderId}/submit")->assertOk();
        $api()->postJson("{$base}/{$orderId}/approve")->assertOk();
        $api()->postJson("{$base}/{$orderId}/order")->assertOk();
        $api()->postJson("{$base}/{$orderId}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => $qty, 'unit_cost' => $cost]],
            'due_at' => '2026-11-10',
        ])->assertOk();

        $receipt = collect($api()->getJson("{$base}/{$orderId}/receipts")->assertOk()->json('data'))->first();

        return [$orderId, $receipt['id'], $receipt['items'][0]['id']];
    }

    public function test_retur_mengurangi_stok_hutang_dan_membuat_jurnal(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        [$orderId, $receiptId, $receiptItemId] = $this->receiveOrder($user, $company, $branch, $supplier, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $returnId = $api()->postJson("{$base}/purchase-orders/{$orderId}/receipts/{$receiptId}/returns", [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 3]],
            'reason' => 'Barang rusak',
            'note' => 'Retur nomor 001',
        ])->assertCreated()
            ->assertJsonPath('data.purchaseOrderId', $orderId)
            ->assertJsonPath('data.goodsReceiptId', $receiptId)
            ->assertJsonPath('data.total', '120000.00')
            ->assertJsonPath('data.reason', 'Barang rusak')
            ->assertJsonPath('data.items.0.qty', '3.000')
            ->assertJsonPath('data.items.0.itemName', 'Oli')
            ->json('data.id');

        $this->assertDatabaseHas('supplier_returns', [
            'id' => $returnId,
            'purchase_order_id' => $orderId,
            'goods_receipt_id' => $receiptId,
            'total' => 120000,
            'reason' => 'Barang rusak',
        ]);
        $this->assertDatabaseHas('supplier_return_items', [
            'supplier_return_id' => $returnId,
            'goods_receipt_item_id' => $receiptItemId,
            'qty' => 3,
            'unit_cost' => 40000,
            'subtotal' => 120000,
        ]);

        $bill = SupplierBill::query()->where('purchase_order_id', $orderId)->firstOrFail();
        $this->assertEquals('280000.00', $bill->amount);
        $this->assertEquals('280000.00', $bill->remaining());

        $stock = $api()->getJson("{$base}/branches/{$branch->id}/stock")->assertOk()->json('data');
        $this->assertEquals('7.000', $stock[0]['qty']);
        $this->assertEquals('40000.00', $stock[0]['avgCost']);

        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $branch->id,
            'item_id' => $item->id,
            'type' => 'retur_supplier',
            'qty' => -3,
            'unit_cost' => 40000,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'supplier_return',
            'source_id' => $returnId,
        ]);

        $receipts = $api()->getJson("{$base}/purchase-orders/{$orderId}/receipts")->assertOk()->json('data');
        $this->assertEquals('3.000', $receipts[0]['items'][0]['qtyReturned']);

        $api()->getJson("{$base}/purchase-orders/{$orderId}/returns")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.total', '120000.00');
    }

    public function test_batas_qty_retur_dan_baris_duplikat(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        [$orderId, $receiptId, $receiptItemId] = $this->receiveOrder($user, $company, $branch, $supplier, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/purchase-orders/{$orderId}/receipts/{$receiptId}/returns";

        $api()->postJson($url, [
            'items' => [
                ['goods_receipt_item_id' => $receiptItemId, 'qty' => 1],
                ['goods_receipt_item_id' => $receiptItemId, 'qty' => 1],
            ],
            'reason' => 'Duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.goods_receipt_item_id');

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 11]],
            'reason' => 'Terlalu banyak',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 3]],
            'reason' => 'Sebagian',
        ])->assertCreated();

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 8]],
            'reason' => 'Melebihi sisa',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 7]],
            'reason' => 'Sisanya',
        ])->assertCreated();

        $this->assertEquals(2, SupplierReturn::query()->where('purchase_order_id', $orderId)->count());
        $bill = SupplierBill::query()->where('purchase_order_id', $orderId)->firstOrFail();
        $this->assertEquals('0.00', $bill->amount);
    }

    public function test_retur_tidak_boleh_melebihi_sisa_hutang_supplier(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        [$orderId, $receiptId, $receiptItemId] = $this->receiveOrder($user, $company, $branch, $supplier, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";
        $url = "{$base}/purchase-orders/{$orderId}/receipts/{$receiptId}/returns";

        $billId = SupplierBill::query()->where('purchase_order_id', $orderId)->value('id');
        $api()->postJson("{$base}/supplier-bills/{$billId}/pay", ['amount' => 300000])->assertOk();

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 3]],
            'reason' => 'Melebihi sisa hutang',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('supplier_returns', 0);
        $this->assertEquals(10, (float) $api()
            ->getJson("{$base}/branches/{$branch->id}/stock")->json('data.0.qty'));

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 2]],
            'reason' => 'Sesuai sisa hutang',
        ])->assertCreated()->assertJsonPath('data.total', '80000.00');

        $bill = SupplierBill::query()->where('purchase_order_id', $orderId)->firstOrFail();
        $this->assertEquals('320000.00', $bill->amount);
        $this->assertEquals('300000.00', $bill->paid_amount);
        $this->assertEquals(20000.0, $bill->remaining());
    }

    public function test_stok_tidak_cukup_dan_po_dibatalkan_ditolak(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        [$orderId, $receiptId, $receiptItemId] = $this->receiveOrder($user, $company, $branch, $supplier, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";
        $url = "{$base}/purchase-orders/{$orderId}/receipts/{$receiptId}/returns";

        $api()->postJson("{$base}/branches/{$branch->id}/stock/movements", [
            'item_id' => $item->id,
            'type' => 'penjualan',
            'qty' => 9,
        ])->assertCreated();

        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 2]],
            'reason' => 'Stok sudah terjual',
        ])->assertUnprocessable()->assertJsonPath('message', 'Stok tidak mencukupi.');

        $this->assertDatabaseCount('supplier_returns', 0);
        $this->assertEquals('400000.00', SupplierBill::query()
            ->where('purchase_order_id', $orderId)->firstOrFail()->amount);

        PurchaseOrder::query()->whereKey($orderId)->update(['status' => 'dibatalkan']);
        $api()->postJson($url, [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 1]],
            'reason' => 'PO sudah batal',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_akses_scope_dan_receipt_tidak_dikenal(): void
    {
        [$user, $company, $branch, $supplier, $item] = $this->setupWorld();
        [$orderId, $receiptId, $receiptItemId] = $this->receiveOrder($user, $company, $branch, $supplier, $item);
        $base = "/api/v1/companies/{$company->id}";
        $url = "{$base}/purchase-orders/{$orderId}/receipts/{$receiptId}/returns";
        $payload = [
            'items' => [['goods_receipt_item_id' => $receiptItemId, 'qty' => 1]],
            'reason' => 'Rusak',
        ];

        $this->app['auth']->forgetGuards();
        $this->postJson($url, $payload)->assertUnauthorized();

        $karyawan = User::factory()->create();
        $this->member($karyawan, $company, $branch->id);
        $this->actingAs($karyawan, 'sanctum')->postJson($url, $payload)->assertForbidden();

        $this->actingAs($user, 'sanctum')->postJson(
            "{$base}/purchase-orders/{$orderId}/receipts/999999/returns",
            $payload,
        )->assertNotFound();

        $this->actingAs($user, 'sanctum')->getJson(
            "{$base}/purchase-orders/999999/returns",
        )->assertNotFound();
    }
}
