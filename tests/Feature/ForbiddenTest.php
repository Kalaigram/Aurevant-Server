<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForbiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutasi_tanpa_izin_ditolak_403(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        app(SubscriptionRepositoryInterface::class)->startTrial($company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $c = "/api/v1/companies/{$company->id}";
        $b = "{$c}/branches/{$branch->id}";

        $targets = [
            ['post', "{$c}/branches", []],
            ['post', "{$c}/categories", []],
            ['post', "{$c}/items", []],
            ['post', "{$c}/service-packages", []],
            ['post', "{$c}/suppliers", []],
            ['post', "{$c}/purchase-orders", []],
            ['post', "{$b}/stock/movements", []],
            ['post', "{$c}/stock-transfers", []],
            ['post', "{$b}/stock/opnames", []],
            ['post', "{$b}/shifts", []],
            ['post', "{$b}/sales", []],
            ['post', "{$c}/customers", []],
            ['post', "{$b}/service-orders", []],
            ['post', "{$c}/access/roles", []],
        ];

        foreach ($targets as [$method, $url, $payload]) {
            $api()->{$method.'Json'}($url, $payload)
                ->assertForbidden()
                ->assertJson(['status' => 'error', 'message' => 'Akses ditolak.', 'data' => null]);
        }

        // assign role juga dijaga
        setPermissionsTeamId($company->id);
        $role = Role::create(['name' => 'Kasir', 'guard_name' => 'web']);
        $api()->postJson("{$c}/access/users/{$user->id}/roles", ['role' => 'Kasir'])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }
}
