<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        return [$user, $company, $branch];
    }

    public function test_biaya_dan_jurnal(): void
    {
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $this->postJson("{$base}/expenses", [])->assertUnauthorized();

        $catId = $api()->postJson("{$base}/expense-categories", ['name' => 'Listrik'])
            ->assertCreated()->json('data.id');

        $id = $api()->postJson("{$base}/expenses", [
            'branch_id' => $branch->id,
            'expense_category_id' => $catId,
            'amount' => 500000,
            'date' => now()->toDateString(),
            'description' => 'Listrik Oktober',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        // belum approve → belum masuk laba rugi
        $today = now()->toDateString();
        $lr = $api()->getJson("{$base}/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $this->assertEquals(0.0, $lr['beban']);

        $api()->postJson("{$base}/expenses/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        $lr = $api()->getJson("{$base}/reports/profit-loss?from={$today}&to={$today}")
            ->assertOk()->json('data');
        $this->assertEquals(500000.0, $lr['beban']);
        $this->assertEquals(-500000.0, $lr['laba_bersih']);
    }

    public function test_biaya_dengan_bukti_dan_aktor(): void
    {
        Storage::fake('public');
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $catId = $api()->postJson("{$base}/expense-categories", ['name' => 'Listrik'])
            ->assertCreated()->json('data.id');

        $response = $api()->post("{$base}/expenses", [
            'branch_id' => $branch->id,
            'expense_category_id' => $catId,
            'amount' => 500000,
            'date' => now()->toDateString(),
            'description' => 'Listrik Oktober',
            'proof' => UploadedFile::fake()->image('nota.jpg'),
        ])->assertCreated();

        $proofUrl = $response->json('data.proofUrl');
        $this->assertStringStartsWith('/storage/bukti-biaya/', $proofUrl);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $proofUrl));

        $this->assertSame($user->id, $response->json('data.userId'));
    }

    public function test_bayar_hutang_dan_aging(): void
    {
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'PT Sumber']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $poId = $api()->postJson("{$base}/purchase-orders", [
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 10, 'unit_price' => 40000]],
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/purchase-orders/{$poId}/submit")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/approve")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/order")->assertOk();
        $api()->postJson("{$base}/purchase-orders/{$poId}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 10, 'unit_cost' => 40000]],
            'due_at' => now()->subDays(40)->toDateString(),
        ])->assertOk();

        $bills = $api()->getJson("{$base}/supplier-bills?unpaid=1")->assertOk()->json('data');
        $this->assertEquals('400000.00', $bills[0]['remaining']);
        $billId = $bills[0]['id'];

        // bayar lebih: tolak
        $api()->postJson("{$base}/supplier-bills/{$billId}/pay", ['amount' => 500000])
            ->assertUnprocessable();
        $api()->postJson("{$base}/supplier-bills/{$billId}/pay", ['amount' => 150000])
            ->assertOk()->assertJsonPath('data.remaining', '250000.00');

        // aging: 250rb di bucket 31-60 (jatuh tempo 40 hari lalu)
        $aging = $api()->getJson("{$base}/supplier-bills-aging")->assertOk()->json('data');
        $this->assertEquals(250000.0, $aging['buckets']['31_60']);
        $this->assertEquals(250000.0, $aging['total']);
    }

    public function test_piutang_dan_aging(): void
    {
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}";

        $id = $api()->postJson("{$base}/receivables", [
            'branch_id' => $branch->id,
            'amount' => 200000,
            'due_at' => now()->addDays(7)->toDateString(),
            'note' => 'Bon pelanggan',
        ])->assertCreated()->json('data.id');

        $api()->postJson("{$base}/receivables/{$id}/pay", ['amount' => 50000])
            ->assertOk()->assertJsonPath('data.remaining', '150000.00');

        $aging = $api()->getJson("{$base}/receivables-aging")->assertOk()->json('data');
        $this->assertEquals(150000.0, $aging['buckets']['lancar']);
        $this->assertEquals(150000.0, $aging['total']);

        // tanpa izin: tolak
        $karyawan = User::factory()->create();
        $this->actingAs($karyawan, 'sanctum')->postJson("{$base}/receivables", [
            'branch_id' => $branch->id, 'amount' => 1000,
        ])->assertForbidden();
    }
}
