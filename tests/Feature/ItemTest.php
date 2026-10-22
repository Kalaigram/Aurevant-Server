<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function auth(): User
    {
        return User::factory()->create();
    }

    private function companyWithBranch(string $suffix = 'A'): array
    {
        $company = Company::create(['name' => "PT {$suffix}", 'slug' => "pt-{$suffix}", 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Pusat']);

        return [$company, $branch];
    }

    public function test_guest_ditolak(): void
    {
        [$company] = $this->companyWithBranch();

        $this->getJson("/api/v1/companies/{$company->id}/items")->assertUnauthorized();
    }

    public function test_crud_item_barang_dan_jasa(): void
    {
        $user = $this->auth();
        [$company] = $this->companyWithBranch();
        $this->owner($user, $company);
        $category = Category::create(['company_id' => $company->id, 'name' => 'Sparepart']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        // barang tanpa satuan: tolak
        $api()->postJson("/api/v1/companies/{$company->id}/items", [
            'code' => 'OLI-01', 'name' => 'Oli', 'type' => 'barang',
        ])->assertUnprocessable()->assertJsonValidationErrors('unit');

        $barangId = $api()->postJson("/api/v1/companies/{$company->id}/items", [
            'code' => 'OLI-01', 'name' => 'Oli', 'type' => 'barang',
            'unit' => 'pcs', 'category_id' => $category->id,
        ])->assertCreated()->assertJsonPath('data.unit', 'pcs')->json('data.id');

        $jasaId = $api()->postJson("/api/v1/companies/{$company->id}/items", [
            'code' => 'SVC-01', 'name' => 'Servis Ringan', 'type' => 'jasa',
            'duration_minutes' => 60, 'track_stock' => false,
            'commission_type' => 'persen', 'commission_value' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.trackStock', false)
            ->assertJsonPath('data.commissionType', 'persen')
            ->assertJsonPath('data.commissionValue', '10.00')
            ->json('data.id');

        // kode duplikat satu company: tolak
        $api()->postJson("/api/v1/companies/{$company->id}/items", [
            'code' => 'OLI-01', 'name' => 'Oli KW', 'type' => 'barang', 'unit' => 'pcs',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        // filter type
        $response = $api()->getJson("/api/v1/companies/{$company->id}/items?type=jasa");
        $response->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.commissionType', 'persen');
        $this->assertEquals(['Servis Ringan'], $response->json('data.*.name'));

        // filter kategori
        $api()->getJson("/api/v1/companies/{$company->id}/items?category_id={$category->id}")
            ->assertOk()->assertJsonPath('meta.total', 1);

        // pencarian kode/nama + nama kategori ikut dikirim
        $cari = $api()->getJson("/api/v1/companies/{$company->id}/items?q=oli")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->assertSame('Sparepart', $cari->json('data.0.categoryName'));

        // jumlah item per kategori ikut dikirim
        $api()->getJson("/api/v1/companies/{$company->id}/categories")
            ->assertOk()
            ->assertJsonPath('data.0.itemCount', 1);

        // update + hapus
        $api()->putJson("/api/v1/companies/{$company->id}/items/{$barangId}", [
            'code' => 'OLI-01', 'name' => 'Oli Premium', 'type' => 'barang', 'unit' => 'pcs',
        ])->assertOk()->assertJsonPath('data.name', 'Oli Premium');

        $api()->deleteJson("/api/v1/companies/{$company->id}/items/{$jasaId}")
            ->assertOk();
        $this->assertSoftDeleted('items', ['id' => $jasaId]);
    }

    public function test_harga_per_cabang_dan_scope(): void
    {
        $user = $this->auth();
        [$a, $cabangA] = $this->companyWithBranch('A');
        [$b, $cabangB] = $this->companyWithBranch('B');
        $this->owner($user, $a);
        $this->owner($user, $b);
        $item = Item::create([
            'company_id' => $a->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $api()->putJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangA->id}", [
            'price' => 55000,
        ])->assertOk()->assertJsonPath('data.price', '55000.00');

        // harga negatif: tolak
        $api()->putJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangA->id}", [
            'price' => -100,
        ])->assertUnprocessable()->assertJsonValidationErrors('price');

        // cabang perusahaan lain: 404
        $api()->putJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangB->id}", [
            'price' => 55000,
        ])->assertNotFound()->assertJsonPath('status', 'error');

        // show memuat harga
        $api()->getJson("/api/v1/companies/{$a->id}/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.prices.0.branchId', $cabangA->id);

        // GET harga cabang (dipakai kasir & katalog web/mobile)
        $api()->getJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangA->id}")
            ->assertOk()
            ->assertJsonPath('data.price', '55000.00')
            ->assertJsonPath('data.branchId', $cabangA->id);

        // cabang tanpa harga: 404
        $cabangA2 = Branch::create(['company_id' => $a->id, 'code' => 'CB2', 'name' => 'Cabang 2']);
        $api()->getJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangA2->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 'error');

        // cabang perusahaan lain: 404
        $api()->getJson("/api/v1/companies/{$a->id}/items/{$item->id}/prices/{$cabangB->id}")
            ->assertNotFound();

        // item perusahaan lain: 404
        $api()->getJson("/api/v1/companies/{$b->id}/items/{$item->id}/prices/{$cabangB->id}")
            ->assertNotFound();
    }
}
