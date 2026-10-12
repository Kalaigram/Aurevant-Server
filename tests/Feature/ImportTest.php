<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Pusat']);

        return [$user, $company, $branch];
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    public function test_impor_item_dry_run_dan_commit(): void
    {
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $url = "/api/v1/companies/{$company->id}/imports/items";

        $this->postJson($url, [])->assertUnauthorized();

        $bad = "code,name,type,unit\n, Tanpa Kode,barang,pcs\nOLI-01,,barang,pcs\n";
        $api()->postJson($url, ['file' => $this->csv($bad), 'dry_run' => true])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data', null);
        $this->assertDatabaseCount('items', 0);

        $good = "code,name,type,unit,category,branch_code,price\nOLI-01,Oli,barang,pcs,Pelumas,HQ,50000\nSVC-01,Servis,jasa,,,,\n";
        $api()->postJson($url, ['file' => $this->csv($good), 'dry_run' => true])
            ->assertOk()->assertJsonPath('data.imported', 0);

        $api()->postJson($url, ['file' => $this->csv($good)])
            ->assertOk()->assertJsonPath('data.imported', 2);
        $this->assertDatabaseHas('items', ['company_id' => $company->id, 'code' => 'OLI-01']);
        $this->assertDatabaseHas('categories', ['company_id' => $company->id, 'name' => 'Pelumas']);
        $this->assertDatabaseHas('item_prices', ['price' => 50000]);

        // duplikat di file + sudah ada: tolak semua
        $api()->postJson($url, ['file' => $this->csv($good)])
            ->assertUnprocessable();
        $this->assertEquals(2, Item::count());
    }

    public function test_impor_stok_supplier_karyawan_dan_ekspor(): void
    {
        [$user, $company, $branch] = $this->setupWorld();
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/imports";

        // tanpa izin impor: 403
        $karyawan = User::factory()->create();
        $this->actingAs($karyawan, 'sanctum')->postJson("{$base}/items", [
            'file' => $this->csv("code,name,type,unit\nX,X,barang,pcs\n"),
        ])->assertForbidden();

        $api()->postJson("{$base}/suppliers", [
            'file' => $this->csv("name,phone\nPT Sumber,031-1\n"),
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $api()->postJson("{$base}/items", [
            'file' => $this->csv("code,name,type,unit\nOLI-01,Oli,barang,pcs\n"),
        ])->assertOk();

        $api()->postJson("{$base}/stock", [
            'file' => $this->csv("item_code,branch_code,qty,unit_cost\nOLI-01,HQ,20,40000\nNGAWUR,HQ,5,1000\n"),
        ])->assertUnprocessable()->assertJsonPath('status', 'error');
        $this->assertDatabaseCount('stock_movements', 0);

        $api()->postJson("{$base}/stock", [
            'file' => $this->csv("item_code,branch_code,qty,unit_cost\nOLI-01,HQ,20,40000\n"),
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $saldo = $api()->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/stock")
            ->assertOk()->json('data');
        $this->assertEquals('20.000', $saldo[0]['qty']);

        $api()->postJson("{$base}/employees", [
            'file' => $this->csv("name,email,branch_code,role\nBudi,budi@x.id,HQ,Kasir\n"),
        ])->assertUnprocessable();

        // buat role dulu lalu impor ulang
        $api()->postJson("/api/v1/companies/{$company->id}/access/roles", [
            'name' => 'Kasir', 'permissions' => ['transaksi.buat'],
        ])->assertCreated();
        $api()->postJson("{$base}/employees", [
            'file' => $this->csv("name,email,branch_code,role\nBudi,budi@x.id,HQ,Kasir\n"),
        ])->assertOk()->assertJsonPath('data.imported', 1);
        $this->assertDatabaseHas('users', ['email' => 'budi@x.id']);
        $this->assertDatabaseHas('employee_assignments', ['branch_id' => $branch->id]);

        // ekspor csv
        $response = $api()->get("/api/v1/companies/{$company->id}/exports/items");
        $response->assertOk();
        $this->assertStringContainsString('code,name,type,unit,category,status', $response->streamedContent());
        $this->assertStringContainsString('OLI-01', $response->streamedContent());

        // template header
        $api()->get("/api/v1/companies/{$company->id}/imports/template/items")
            ->assertOk();
    }
}
