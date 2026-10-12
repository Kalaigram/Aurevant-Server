<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        setPermissionsTeamId($company->id);
        foreach (PermissionCatalog::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $kasirRole = Role::create(['name' => 'Kasir', 'guard_name' => 'web', 'company_id' => $company->id]);
        $kasirRole->syncPermissions(RoleTemplates::permissionsFor('Kasir', PermissionCatalog::all()));
        $kasir = User::factory()->create();
        $kasir->assignRole($kasirRole);
        $kasir = $kasir->fresh();

        $jasa = Item::create([
            'company_id' => $company->id, 'code' => 'JASA-01', 'name' => 'Servis',
            'type' => 'jasa', 'unit' => 'paket',
        ]);
        ItemPrice::create(['item_id' => $jasa->id, 'branch_id' => $branch->id, 'price' => 100000]);

        CashShift::create([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'user_id' => $owner->id, 'status' => 'terbuka',
            'opening_cash' => 0, 'opened_at' => now(),
        ]);

        return [$owner, $kasir, $company, $branch, $jasa];
    }

    private function makeSale(object $api, int $companyId, int $branchId, int $itemId, array $extra = []): int
    {
        return $api->postJson("/api/v1/companies/{$companyId}/branches/{$branchId}/sales", [
            'items' => [['item_id' => $itemId, 'qty' => 1]],
            'payments' => [['method' => 'tunai', 'amount' => 100000]],
        ] + $extra)->assertCreated()->json('data.id');
    }

    public function test_minta_void_lalu_disetujui(): void
    {
        [$owner, $kasir, $company, $branch, $jasa] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";
        $boss = fn () => $this->actingAs($owner, 'sanctum');
        $staff = fn () => $this->actingAs($kasir, 'sanctum');

        $this->postJson("{$base}/approvals", [])->assertUnauthorized();

        $saleId = $this->makeSale($boss(), $company->id, $branch->id, $jasa->id);

        // kasir tak bisa void langsung
        $staff()->postJson("{$base}/branches/{$branch->id}/sales/{$saleId}/void", ['reason' => 'Salah'])
            ->assertForbidden();

        $id = $staff()->postJson("{$base}/approvals", [
            'action' => 'sale.void',
            'entity_id' => $saleId,
            'payload' => ['reason' => 'Salah input'],
        ])->assertCreated()->assertJsonPath('data.status', 'diajukan')->json('data.id');

        // duplikat: tolak
        $staff()->postJson("{$base}/approvals", [
            'action' => 'sale.void',
            'entity_id' => $saleId,
            'payload' => ['reason' => 'Lagi'],
        ])->assertUnprocessable();

        // kasir tak bisa menyetujui
        $staff()->postJson("{$base}/approvals/{$id}/approve")->assertForbidden();

        // inbox pemilik: ada
        $boss()->getJson("{$base}/approvals?status=diajukan")->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $boss()->postJson("{$base}/approvals/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'disetujui');

        // nota jadi void; setujui ulang: tolak
        $boss()->getJson("{$base}/branches/{$branch->id}/sales/{$saleId}")->assertOk()
            ->assertJsonPath('data.status', 'void');
        $boss()->postJson("{$base}/approvals/{$id}/approve")->assertUnprocessable();
    }

    public function test_diskon_lewat_mesin_dan_po_masuk_inbox(): void
    {
        [$owner, $kasir, $company, $branch, $jasa] = $this->setupWorld();
        $base = "/api/v1/companies/{$company->id}";
        $boss = fn () => $this->actingAs($owner, 'sanctum');
        $staff = fn () => $this->actingAs($kasir, 'sanctum');

        // nota diskon diajukan kasir, dijalankan saat disetujui
        $id = $staff()->postJson("{$base}/approvals", [
            'action' => 'sale.discount',
            'payload' => [
                'branch_id' => $branch->id,
                'lines' => [['item_id' => $jasa->id, 'qty' => 1, 'discount_percent' => 10]],
                'payments' => [['method' => 'tunai', 'amount' => 90000]],
            ],
        ])->assertCreated()->json('data.id');

        $boss()->postJson("{$base}/approvals/{$id}/approve")->assertOk();

        $sales = $boss()->getJson("{$base}/branches/{$branch->id}/sales")->assertOk()->json('data');
        $this->assertEquals('90000.00', $sales[0]['grandTotal']);

        // PO diajukan (jalur lama) otomatis masuk inbox
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'PT Sumber']);
        $poId = $boss()->postJson("{$base}/purchase-orders", [
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'items' => [['item_id' => $jasa->id, 'qty_ordered' => 1, 'unit_price' => 50000]],
        ])->assertCreated()->json('data.id');
        $boss()->postJson("{$base}/purchase-orders/{$poId}/submit")->assertOk();

        $inbox = $boss()->getJson("{$base}/approvals?status=diajukan&action=po.approve")
            ->assertOk()->json('data');
        $this->assertSame($poId, $inbox[0]['entityId']);

        // setujui dari inbox menjalankan transisi PO
        $boss()->postJson("{$base}/approvals/{$inbox[0]['id']}/approve")->assertOk();
        $boss()->getJson("{$base}/purchase-orders/{$poId}")->assertOk()
            ->assertJsonPath('data.status', 'disetujui');
    }
}
