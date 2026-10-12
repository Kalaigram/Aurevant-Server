<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class BillingInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_terbit_dan_bayar_perpanjang(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $plan = Plan::create(['name' => 'Pro', 'price' => 99000]);
        $base = "/api/v1/companies/{$company->id}";

        // owner boleh lihat, bukan super admin dilarang terbitkan
        $this->actingAs($user, 'sanctum')->getJson("{$base}/subscription-invoices")
            ->assertOk()->assertJsonPath('data', []);
        $this->actingAs($user, 'sanctum')->postJson("{$base}/subscription-invoices", [
            'amount' => 99000, 'due_at' => now()->addDays(7)->toDateString(),
        ])->assertForbidden();

        $id = $this->actingAs($admin, 'sanctum')->postJson("{$base}/subscription-invoices", [
            'plan_id' => $plan->id,
            'amount' => 99000,
            'due_at' => now()->addDays(7)->toDateString(),
        ])->assertCreated()->assertJsonPath('data.status', 'menunggu')->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("{$base}/subscription-invoices/{$id}/pay")
            ->assertOk()->assertJsonPath('data.status', 'lunas');

        // bayar ganda: tolak
        $this->actingAs($admin, 'sanctum')
            ->postJson("{$base}/subscription-invoices/{$id}/pay")
            ->assertUnprocessable();

        $sub = $this->actingAs($user, 'sanctum')->getJson("{$base}/subscription")
            ->assertOk()->json('data');
        $this->assertSame('aktif', $sub['status']);
        $this->assertTrue(now()->addDays(29)->toDateString() <= $sub['endsAt']);
    }
}
