<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $gudang = User::factory()->create();
        $gudang2 = User::factory()->create();
        $kasir = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branchA = Branch::create(['company_id' => $company->id, 'code' => 'A', 'name' => 'Cabang A']);
        $branchB = Branch::create(['company_id' => $company->id, 'code' => 'B', 'name' => 'Cabang B']);
        $item = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);

        setPermissionsTeamId($company->id);
        $roleGudang = Role::create(['name' => 'Staf Gudang', 'guard_name' => 'web']);
        $roleGudang->syncPermissions(['stok.transfer']);
        $gudang->assignRole($roleGudang);
        $gudang2->assignRole($roleGudang);

        $roleKasir = Role::create(['name' => 'Kasir', 'guard_name' => 'web']);
        $roleKasir->syncPermissions(['transaksi.buat']);
        $kasir->assignRole($roleKasir);

        return [$owner, $gudang, $gudang2, $kasir, $company, $branchA, $branchB, $item];
    }

    public function test_trigger_preferensi_baca_dan_perangkat(): void
    {
        [$owner, $gudang, $gudang2, $kasir, $company, $branchA, $branchB, $item] = $this->setupWorld();

        // gudang matikan notif transfer.diajukan
        $this->actingAs($gudang, 'sanctum')->putJson('/api/v1/notification-preferences', [
            'type' => 'transfer.diajukan', 'enabled' => false,
        ])->assertOk();

        // owner mengajukan transfer A→B (butuh stok dulu)
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branchA->id}/stock/movements",
            ['item_id' => $item->id, 'type' => 'pembelian', 'qty' => 50, 'unit_cost' => 40000]
        )->assertCreated();
        $transferId = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/companies/{$company->id}/stock-transfers", [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'items' => [['item_id' => $item->id, 'qty' => 10]],
        ])->assertCreated()->json('data.id');
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/stock-transfers/{$transferId}/submit"
        )->assertOk();

        $unread = fn ($user) => $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')->json('data.unread');

        // pengaju dikecualikan, pref off dilewati, tanpa izin dilewati
        $this->assertEquals(0, $unread($owner));
        $this->assertEquals(0, $unread($gudang));
        $this->assertEquals(0, $unread($kasir));

        // gudang2 (izin + pref nyala) dapat 1, lalu dibaca
        $this->assertEquals(1, $unread($gudang2));
        $list = $this->actingAs($gudang2, 'sanctum')->getJson('/api/v1/notifications')
            ->assertOk()->json('data');
        $this->assertEquals('transfer.diajukan', $list[0]['type']);
        $this->actingAs($gudang2, 'sanctum')
            ->postJson("/api/v1/notifications/{$list[0]['id']}/read")->assertOk();
        $this->assertEquals(0, $unread($gudang2));

        // kasir tanpa izin stok.transfer: submit ditolak, tak ada notif baru
        $this->actingAs($kasir, 'sanctum')->postJson("/api/v1/companies/{$company->id}/stock-transfers", [
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'items' => [['item_id' => $item->id, 'qty' => 5]],
        ])->assertForbidden();
        $this->assertEquals(0, $unread($gudang2));
    }

    public function test_baca_preferensi_dan_token(): void
    {
        [$owner] = $this->setupWorld();
        $api = fn () => $this->actingAs($owner, 'sanctum');

        $this->getJson('/api/v1/notifications')->assertUnauthorized();

        $api()->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'transfer.diajukan')
            ->assertJsonPath('data.0.enabled', true);

        $api()->putJson('/api/v1/notification-preferences', [
            'type' => 'sale.void', 'enabled' => false,
        ])->assertOk()->assertJsonPath('data.enabled', false);

        $api()->putJson('/api/v1/notification-preferences', [
            'type' => 'ngawur', 'enabled' => false,
        ])->assertNotFound();

        $api()->postJson('/api/v1/device-tokens', ['token' => 'fcm-abc', 'platform' => 'android'])
            ->assertOk();
        $this->assertDatabaseHas('user_device_tokens', ['user_id' => $owner->id, 'token' => 'fcm-abc']);

        // daftar ulang idempotent
        $api()->postJson('/api/v1/device-tokens', ['token' => 'fcm-abc', 'platform' => 'android'])
            ->assertOk();
        $this->assertEquals(1, UserDeviceToken::where('token', 'fcm-abc')->count());

        $api()->deleteJson('/api/v1/device-tokens', ['token' => 'fcm-abc'])->assertOk();
        $this->assertDatabaseMissing('user_device_tokens', ['token' => 'fcm-abc']);

        // baca notifikasi milik sendiri saja
        $otherCompany = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherUser = User::factory()->create();
        AppNotification::create([
            'company_id' => $otherCompany->id, 'user_id' => $otherUser->id,
            'type' => 'x', 'title' => 'bukan milikku',
        ]);
        $api()->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 0);
        $api()->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.read', 0);
    }
}
