<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $manager = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $a = Branch::create(['company_id' => $company->id, 'code' => 'A', 'name' => 'Cabang A']);
        $b = Branch::create(['company_id' => $company->id, 'code' => 'B', 'name' => 'Cabang B']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $a->id, 'price' => 50000]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $b->id, 'price' => 55000]);
        EmployeeAssignment::create([
            'user_id' => $manager->id, 'company_id' => $company->id,
            'branch_id' => $a->id, 'started_at' => '2026-10-01',
        ]);
        $this->owner($manager, $company);

        return [$manager, $company, $a, $b, $item];
    }

    public function test_manajer_hanya_bisa_di_cabangnya(): void
    {
        [$manager, $company, $a, $b, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($manager, 'sanctum');

        // cabang sendiri: boleh
        $api()->putJson("/api/v1/companies/{$company->id}/branches/{$a->id}", [
            'code' => 'A', 'name' => 'Cabang A Baru',
        ])->assertOk();

        // cabang lain: 403 kontrak
        $api()->putJson("/api/v1/companies/{$company->id}/branches/{$b->id}", [
            'code' => 'B', 'name' => 'Cabang B Baru',
        ])->assertForbidden()->assertJson([
            'status' => 'error', 'message' => 'Akses ditolak.', 'data' => null,
        ]);

        // shift + jual di cabang sendiri boleh
        $api()->postJson("/api/v1/companies/{$company->id}/branches/{$a->id}/shifts", ['opening_cash' => 0])
            ->assertCreated();

        // shift di cabang lain: 403
        $api()->postJson("/api/v1/companies/{$company->id}/branches/{$b->id}/shifts", ['opening_cash' => 0])
            ->assertForbidden()->assertJsonPath('status', 'error');
    }

    public function test_transfer_antar_cabang_butuh_sisi_relevan(): void
    {
        [$manager, $company, $a, $b, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($manager, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/stock-transfers";

        // buat + kirim dari cabang sendiri
        $this->actingAs($manager, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$a->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => 100, 'unit_cost' => 40000]
        )->assertCreated();

        $id = $api()->postJson($base, [
            'from_branch_id' => $a->id,
            'to_branch_id' => $b->id,
            'items' => [['item_id' => $item->id, 'qty' => 10]],
        ])->assertCreated()->json('data.id');

        // transfer keluar dari cabang lain: 403 (from/to tidak dalam scope)
        $other = Branch::create(['company_id' => $company->id, 'code' => 'C', 'name' => 'Cabang C']);
        $api()->postJson($base, [
            'from_branch_id' => $b->id,
            'to_branch_id' => $other->id,
            'items' => [['item_id' => $item->id, 'qty' => 10]],
        ])->assertForbidden();

        // lihat transfer A→B: boleh (sisi asal dalam scope)
        $api()->getJson("{$base}/{$id}")->assertOk();

        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/approve")->assertOk();
        $api()->postJson("{$base}/{$id}/ship")->assertOk();

        // terima butuh sisi tujuan (B): manajer A ditolak
        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 10]],
        ])->assertForbidden()->assertJsonPath('status', 'error');
    }

    public function test_tanpa_penempatan_ditolak(): void
    {
        [$manager, $company, $a, $b] = $this->setupWorld();
        $api = fn () => $this->actingAs($manager, 'sanctum');

        EmployeeAssignment::where('user_id', $manager->id)->delete();

        $api()->getJson("/api/v1/companies/{$company->id}/branches/{$b->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Akses ditolak.');

        $api()->putJson("/api/v1/companies/{$company->id}/branches/{$b->id}", [
            'code' => 'B', 'name' => 'Bebas',
        ])->assertForbidden();
    }

    public function test_po_terikat_cabang(): void
    {
        [$manager, $company, $a, $b, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($manager, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/purchase-orders";
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'S']);

        // PO untuk cabang B: 403
        $api()->postJson($base, [
            'branch_id' => $b->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 5, 'unit_price' => 40000]],
        ])->assertForbidden();

        // PO untuk cabang A: boleh
        $api()->postJson($base, [
            'branch_id' => $a->id,
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $item->id, 'qty_ordered' => 5, 'unit_price' => 40000]],
        ])->assertCreated();
    }
}
