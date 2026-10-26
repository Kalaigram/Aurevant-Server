<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class JasaProsesTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function userWithTemplate(Company $company, string $template, int $branchId): User
    {
        setPermissionsTeamId($company->id);
        foreach (PermissionCatalog::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::create(['name' => $template.'-'.uniqid(), 'guard_name' => 'web', 'company_id' => $company->id]);
        $role->syncPermissions(RoleTemplates::permissionsFor($template, PermissionCatalog::all()));
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->member($user, $company, $branchId);

        return $user->fresh();
    }

    public function test_pelaksana_proses_tanpa_buat_nota(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'BKL', 'name' => 'Bengkel']);
        $customer = Customer::create(['company_id' => $company->id, 'name' => 'Pak Dudi']);
        $servis = Item::create([
            'company_id' => $company->id, 'code' => 'SVC-01', 'name' => 'Servis',
            'type' => 'jasa', 'unit' => 'paket',
        ]);
        ItemPrice::create(['item_id' => $servis->id, 'branch_id' => $branch->id, 'price' => 100000]);

        $pelaksana = $this->userWithTemplate($company, 'Pelaksana Jasa', $branch->id);
        $kasir = $this->userWithTemplate($company, 'Kasir', $branch->id);

        $boss = fn () => $this->actingAs($owner, 'sanctum');
        $tek = fn () => $this->actingAs($pelaksana, 'sanctum');
        $kas = fn () => $this->actingAs($kasir, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/service-orders";

        $id = $boss()->postJson($base, [
            'customer_id' => $customer->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');

        // pelaksana: proses + selesai boleh
        $tek()->postJson("{$base}/{$id}/process", ['assignee_id' => $pelaksana->id])
            ->assertOk()->assertJsonPath('data.status', 'diproses');
        $tek()->postJson("{$base}/{$id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'selesai');

        // pelaksana: bikin nota biasa dan tarik bayaran tidak boleh
        $tek()->postJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/sales", [
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
            'payments' => [['method' => 'tunai', 'amount' => 100000]],
        ])->assertForbidden();

        // kasir: proses tidak boleh (tanpa jasa.proses)
        $id2 = $boss()->postJson($base, [
            'customer_id' => $customer->id,
            'items' => [['item_id' => $servis->id, 'qty' => 1]],
        ])->assertCreated()->json('data.id');
        $kas()->postJson("{$base}/{$id2}/process", ['assignee_id' => $kasir->id])->assertForbidden();
    }
}
