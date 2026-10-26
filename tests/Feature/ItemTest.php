<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        // update + hapus; komisi null dari form dinormalisasi ke 0
        $api()->putJson("/api/v1/companies/{$company->id}/items/{$barangId}", [
            'code' => 'OLI-01', 'name' => 'Oli Premium', 'type' => 'barang', 'unit' => 'pcs',
            'commission_type' => null, 'commission_value' => null,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Oli Premium')
            ->assertJsonPath('data.commissionType', null)
            ->assertJsonPath('data.commissionValue', '0.00');

        $api()->deleteJson("/api/v1/companies/{$company->id}/items/{$jasaId}")
            ->assertOk();
        $this->assertSoftDeleted('items', ['id' => $jasaId]);
    }

    public function test_gambar_item_upload_ganti_hapus_dan_scope(): void
    {
        Storage::fake('public');

        $user = $this->auth();
        [$company] = $this->companyWithBranch('A');
        [$other] = $this->companyWithBranch('B');
        $this->owner($user, $company);
        $this->owner($user, $other);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/items/{$item->id}/image";

        $upload = $api()->post($url, [
            'image' => UploadedFile::fake()->image('oli.jpg', 800, 600),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imagePath', fn ($path) => is_string($path) && str_starts_with($path, 'item-images/'));

        $firstPath = $upload->json('data.imagePath');
        $this->assertSame('/storage/'.$firstPath, $upload->json('data.imageUrl'));
        Storage::disk('public')->assertExists($firstPath);

        $api()->getJson("/api/v1/companies/{$company->id}/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.imageUrl', '/storage/'.$firstPath);

        $replacement = $api()->post($url, [
            'image' => UploadedFile::fake()->image('oli-baru.png', 640, 480),
        ], ['Accept' => 'application/json'])->assertOk();

        $secondPath = $replacement->json('data.imagePath');
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        // File bukan gambar ditolak.
        $api()->post($url, [
            'image' => UploadedFile::fake()->create('catatan.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        // Item perusahaan lain tetap 404 walau user punya akses ke perusahaan itu.
        $foreignUrl = "/api/v1/companies/{$other->id}/items/{$item->id}/image";
        $api()->post($foreignUrl, [
            'image' => UploadedFile::fake()->image('asing.jpg'),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $api()->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('data.imagePath', null)
            ->assertJsonPath('data.imageUrl', null);
        Storage::disk('public')->assertMissing($secondPath);

        // Hapus item juga membersihkan file gambar.
        $thirdPath = $api()->post($url, [
            'image' => UploadedFile::fake()->image('oli-final.jpg'),
        ], ['Accept' => 'application/json'])->json('data.imagePath');
        Storage::disk('public')->assertExists($thirdPath);

        $api()->deleteJson("/api/v1/companies/{$company->id}/items/{$item->id}")
            ->assertOk();
        Storage::disk('public')->assertMissing($thirdPath);
        $this->assertSoftDeleted('items', ['id' => $item->id]);
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
