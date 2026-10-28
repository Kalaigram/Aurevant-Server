<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function auth(): User
    {
        return User::factory()->create();
    }

    private function setupWorld(string $suffix = 'A'): array
    {
        $company = Company::create(['name' => "PT {$suffix}", 'slug' => "pt-{$suffix}", 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'Pusat']);
        $servis = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-01', 'name' => 'Servis Ringan',
            'type' => 'jasa', 'track_stock' => false,
        ]);
        $detail = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-02', 'name' => 'Cuci Detailing',
            'type' => 'jasa', 'track_stock' => false,
        ]);
        $barang = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        return [$company, $branch, $servis, $detail, $barang];
    }

    public function test_guest_ditolak(): void
    {
        [$company] = $this->setupWorld();

        $this->getJson("/api/v1/companies/{$company->id}/service-packages")->assertUnauthorized();
    }

    public function test_crud_paket_jasa_dan_scope_company(): void
    {
        $user = $this->auth();
        [$company, $branch, $servis, $detail, $barang] = $this->setupWorld('A');
        [$other, $otherBranch, $otherServis] = $this->setupWorld('B');
        $this->owner($user, $company);
        $this->owner($user, $other);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/service-packages";

        $id = $api()->postJson($base, [
            'code' => 'PAK-01',
            'name' => 'Paket Hemat',
            'price' => 120000,
            'status' => 'aktif',
            'items' => [
                ['item_id' => $servis->id, 'qty' => 1],
                ['item_id' => $detail->id, 'qty' => 2],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.code', 'PAK-01')
            ->assertJsonPath('data.price', '120000.00')
            ->assertJsonPath('data.itemCount', 2)
            ->assertJsonPath('data.items.0.itemName', 'Servis Ringan')
            ->json('data.id');

        $api()->postJson($base, [
            'code' => 'PAK-01', 'name' => 'Paket Lain', 'price' => 10000,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $api()->postJson($base, [
            'code' => 'PAK-02', 'name' => 'Paket Barang', 'price' => 10000,
            'items' => [['item_id' => $barang->id, 'qty' => 1]],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        $api()->postJson($base, [
            'code' => 'PAK-03', 'name' => 'Paket Luar', 'price' => 10000,
            'items' => [['item_id' => $otherServis->id, 'qty' => 1]],
        ])->assertNotFound();

        $api()->getJson("{$base}?q=hemat")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.itemCount', 2);

        $api()->putJson("{$base}/{$id}", [
            'code' => 'PAK-01',
            'name' => 'Paket Hemat Plus',
            'price' => 150000,
            'items' => [['item_id' => $detail->id, 'qty' => 1]],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Paket Hemat Plus')
            ->assertJsonPath('data.price', '150000.00')
            ->assertJsonPath('data.itemCount', 1)
            ->assertJsonPath('data.items.0.itemId', $detail->id);

        $api()->getJson("/api/v1/companies/{$other->id}/service-packages")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $api()->putJson("/api/v1/companies/{$other->id}/service-packages/{$id}", [
            'code' => 'PAK-01', 'name' => 'Bukan', 'price' => 1,
            'items' => [['item_id' => $otherServis->id, 'qty' => 1]],
        ])->assertNotFound();

        $api()->deleteJson("/api/v1/companies/{$other->id}/service-packages/{$id}")->assertNotFound();
        $api()->deleteJson("{$base}/{$id}")->assertOk();
        $this->assertDatabaseMissing('service_packages', ['id' => $id]);
    }

    public function test_paket_nonaktif_tidak_bisa_dipakai_di_order(): void
    {
        $user = $this->auth();
        [$company, $branch, $servis] = $this->setupWorld();
        $this->owner($user, $company);
        $package = ServicePackage::create([
            'company_id' => $company->id, 'code' => 'PAK-OFF', 'name' => 'Paket Off',
            'price' => 50000, 'status' => 'nonaktif',
        ]);
        $package->items()->create(['item_id' => $servis->id, 'qty' => 1]);

        $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders",
            ['package_id' => $package->id]
        )->assertUnprocessable()->assertJsonValidationErrors('package_id');
    }
}
