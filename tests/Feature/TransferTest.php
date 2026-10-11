<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $gudang = Branch::create(['company_id' => $company->id, 'code' => 'GUD', 'name' => 'Gudang', 'type' => 'gudang']);
        $toko = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        return [$user, $company, $gudang, $toko, $item];
    }

    private function stockIn(User $user, Company $company, Branch $branch, Item $item, float $qty = 100, float $cost = 50000): void
    {
        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => $qty, 'unit_cost' => $cost]
        )->assertCreated();
    }

    private function balance(User $user, Company $company, Branch $branch): array
    {
        return $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->json('data');
    }

    public function test_alur_penuh_dengan_selisih_terima(): void
    {
        [$user, $company, $gudang, $toko, $item] = $this->setupWorld();
        $this->stockIn($user, $company, $gudang, $item);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/stock-transfers";

        $id = $api()->postJson($base, [
            'from_branch_id' => $gudang->id,
            'to_branch_id' => $toko->id,
            'items' => [['item_id' => $item->id, 'qty' => 40]],
            'note' => 'Restok toko',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        // approve langsung tanpa submit: tolak
        $api()->postJson("{$base}/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('status', 'error');

        $api()->postJson("{$base}/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'diajukan');
        $api()->postJson("{$base}/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'disetujui');

        // gudang belum berkurang sebelum dikirim
        $this->assertEquals('100.000', $this->balance($user, $company, $gudang)[0]['qty']);

        $api()->postJson("{$base}/{$id}/ship")
            ->assertOk()->assertJsonPath('data.status', 'dikirim');

        $this->assertEquals('60.000', $this->balance($user, $company, $gudang)[0]['qty']);
        $this->assertEquals([], $this->balance($user, $company, $toko));

        // terima sebagian (35 dari 40, 5 hilang di jalan)
        $api()->postJson("{$base}/{$id}/receive", [
            'items' => [['item_id' => $item->id, 'qty_received' => 35]],
        ])->assertOk()->assertJsonPath('data.status', 'diterima');

        $tokoBalance = $this->balance($user, $company, $toko);
        $this->assertEquals('35.000', $tokoBalance[0]['qty']);
        $this->assertEquals('50000.00', $tokoBalance[0]['avgCost']);

        $detail = $api()->getJson("{$base}/{$id}")->assertOk()->json('data');
        $this->assertEquals('40.000', $detail['items'][0]['qtyRequested']);
        $this->assertEquals('35.000', $detail['items'][0]['qtyReceived']);
    }

    public function test_kirim_stok_kurang_ditolak_dan_bisa_batal(): void
    {
        [$user, $company, $gudang, $toko, $item] = $this->setupWorld();
        $this->stockIn($user, $company, $gudang, $item, 10);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/stock-transfers";

        $id = $api()->postJson($base, [
            'from_branch_id' => $gudang->id,
            'to_branch_id' => $toko->id,
            'items' => [['item_id' => $item->id, 'qty' => 50]],
        ])->assertCreated()->json('data.id');

        $api()->postJson("{$base}/{$id}/submit")->assertOk();
        $api()->postJson("{$base}/{$id}/approve")->assertOk();
        $api()->postJson("{$base}/{$id}/ship")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Stok tidak mencukupi.');

        // status tetap disetujui, gudang utuh
        $api()->getJson("{$base}/{$id}")->assertJsonPath('data.status', 'disetujui');
        $this->assertEquals('10.000', $this->balance($user, $company, $gudang)[0]['qty']);

        // batalkan dari diajukan/disetujui? batal hanya draft/diajukan → 422, lalu alur batal yg benar
        $api()->postJson("{$base}/{$id}/cancel")
            ->assertUnprocessable()->assertJsonPath('status', 'error');
    }

    public function test_validasi_cabang_dan_guest(): void
    {
        [$user, $company, $gudang, $toko, $item] = $this->setupWorld();
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherBranch = Branch::create(['company_id' => $other->id, 'code' => 'HQ', 'name' => 'Pusat']);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/stock-transfers";

        $this->postJson($base, [])->assertUnauthorized();

        // asal = tujuan: tolak
        $api()->postJson($base, [
            'from_branch_id' => $gudang->id,
            'to_branch_id' => $gudang->id,
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ])->assertUnprocessable()->assertJsonValidationErrors('to_branch_id');

        // cabang perusahaan lain: 404
        $api()->postJson($base, [
            'from_branch_id' => $gudang->id,
            'to_branch_id' => $otherBranch->id,
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ])->assertNotFound()->assertJsonPath('status', 'error');

        // batal dari draft: boleh
        $id = $api()->postJson($base, [
            'from_branch_id' => $gudang->id,
            'to_branch_id' => $toko->id,
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ])->assertCreated()->json('data.id');
        $api()->postJson("{$base}/{$id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'dibatalkan');
    }
}
