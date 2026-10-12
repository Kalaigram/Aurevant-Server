<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $item->id, 'branch_id' => $branch->id, 'price' => 50000]);

        return [$owner, $company, $branch, $item];
    }

    public function test_aksi_penting_tercatat_dan_bisa_dilihat(): void
    {
        [$owner, $company, $branch, $item] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');

        // 1. ubah harga
        $api()->putJson("/api/v1/companies/{$company->id}/items/{$item->id}/prices/{$branch->id}", [
            'price' => 55000,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'price.set',
            'entity_type' => 'item',
            'entity_id' => $item->id,
        ]);

        // 2. void nota
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 0]
        )->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => 10, 'unit_cost' => 40000]
        )->assertCreated();
        $notaId = $api()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/sales", [
            'items' => [['item_id' => $item->id, 'qty' => 2]],
            'payments' => [['method' => 'tunai', 'amount' => 110000]],
        ])->assertCreated()->json('data.id');
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/{$notaId}/void",
            ['reason' => 'Salah']
        )->assertOk();

        // 3. baca log dengan filter
        $response = $api()->getJson("/api/v1/companies/{$company->id}/audit-logs?action=sale.void")
            ->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $this->assertEquals('lunas', $response->json('data.0.before.status'));
        $this->assertEquals('void', $response->json('data.0.after.status'));
        $this->assertEquals($owner->id, $response->json('data.0.userId'));
        $this->assertNotNull($response->json('data.0.ip'));

        // 4. tanpa izin audit.lihat: 403 (cabut dulu)
        $staff = User::factory()->create();
        $apiStaff = fn () => $this->actingAs($staff, 'sanctum');
        $apiStaff()->getJson("/api/v1/companies/{$company->id}/audit-logs")->assertForbidden();

        // 5. isolasi: log company lain tak terlihat
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $this->owner($owner, $other);
        AuditLog::create([
            'company_id' => $other->id, 'action' => 'sale.void',
            'entity_type' => 'sale', 'entity_id' => 9,
        ]);
        $api()->getJson("/api/v1/companies/{$company->id}/audit-logs")
            ->assertOk()
            ->assertJsonMissing(['entity_id' => 9]);
    }
}
