<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class BillingAutoTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_generate_lewati_duplikat_dan_tanpa_paket(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Pro', 'price' => 99000]);

        $segera = Company::create(['name' => 'PT Segera', 'slug' => 'pt-segera', 'status' => 'aktif']);
        $this->owner($user, $segera);
        Subscription::query()->where('company_id', $segera->id)->update([
            'plan_id' => $plan->id,
            'status' => 'aktif',
            'ends_at' => now()->addDays(3)->toDateString(),
        ]);

        $nanti = Company::create(['name' => 'PT Nanti', 'slug' => 'pt-nanti', 'status' => 'aktif']);
        Subscription::create([
            'company_id' => $nanti->id, 'plan_id' => $plan->id,
            'status' => 'aktif', 'ends_at' => now()->addDays(60)->toDateString(),
        ]);

        $tanpaPaket = Company::create(['name' => 'PT Trial', 'slug' => 'pt-trial', 'status' => 'aktif']);
        Subscription::create([
            'company_id' => $tanpaPaket->id, 'plan_id' => null,
            'status' => 'trial', 'trial_ends_at' => now()->addDays(2)->toDateString(),
        ]);

        $this->artisan('billing:generate')->assertSuccessful();

        $this->assertSame(1, SubscriptionInvoice::query()->where('company_id', $segera->id)->count());
        $this->assertSame(0, SubscriptionInvoice::query()->where('company_id', $nanti->id)->count());
        $this->assertSame(0, SubscriptionInvoice::query()->where('company_id', $tanpaPaket->id)->count());

        $invoice = SubscriptionInvoice::query()->where('company_id', $segera->id)->first();
        $this->assertSame('menunggu', $invoice->status);
        $this->assertEquals(99000.0, (float) $invoice->amount);

        // jalan lagi: tidak duplikat
        $this->artisan('billing:generate')->assertSuccessful();
        $this->assertSame(1, SubscriptionInvoice::query()->where('company_id', $segera->id)->count());
    }
}
