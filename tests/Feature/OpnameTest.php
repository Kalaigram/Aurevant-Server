<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpnameTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        return [$user, $company, $branch, $item];
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty = 100): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => 50000]
        )->assertCreated();
    }

    private function balance(User $user, Company $company, Branch $branch): string
    {
        return $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->json('data.0.qty');
    }

    public function test_opname_kurang_menerapkan_selisih(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $this->stockIn($user, $company, $branch, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/opnames";

        $id = $api()->postJson($base, [
            'items' => [['item_id' => $item->id, 'qty_counted' => 95]],
            'note' => 'Opname bulanan',
        ])->assertCreated()
            ->assertJsonPath('data.items.0.qtySystem', '100.000')
            ->assertJsonPath('data.items.0.diff', '-5')
            ->json('data.id');

        // approve langsung: tolak
        $api()->postJson("{$base}/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('status', 'error');

        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'disetujui');

        $this->assertEquals('95.000', $this->balance($user, $company, $branch));

        // approve dua kali: tolak
        $api()->postJson("{$base}/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('status', 'error');
    }

    public function test_opname_lebih_dan_tolak(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $this->stockIn($user, $company, $branch, $item, 10);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/opnames";

        $id = $api()->postJson($base, [
            'items' => [['item_id' => $item->id, 'qty_counted' => 12]],
        ])->assertCreated()->json('data.id');

        // tolak dari draft: tidak bisa, harus diajukan dulu
        $api()->postJson("{$base}/{$id}/reject")->assertUnprocessable();
        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/reject")
            ->assertOk()->assertJsonPath('data.status', 'ditolak');

        // stok tidak berubah
        $this->assertEquals('10.000', $this->balance($user, $company, $branch));
    }

    public function test_guest_dan_scope_ditolak(): void
    {
        [$user, $company, $branch, $item] = $this->setupWorld();
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherBranch = Branch::create(['company_id' => $other->id, 'code' => 'HQ', 'name' => 'Pusat']);

        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/opnames";
        $this->postJson($base, [])->assertUnauthorized();

        $api = fn () => $this->actingAs($user, 'sanctum');
        // cabang perusahaan lain: 404
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$otherBranch->id}/stock/opnames",
            ['items' => [['item_id' => $item->id, 'qty_counted' => 1]]]
        )->assertNotFound();
    }
}
