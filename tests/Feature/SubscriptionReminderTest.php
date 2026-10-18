<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Billing\Mail\SubscriptionReminderMail;
use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class SubscriptionReminderTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: Company, 2: Subscription} */
    private function makeCompany(string $name, string $slug): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => $name, 'slug' => $slug, 'status' => 'aktif']);
        $this->owner($owner, $company);

        return [$owner, $company, Subscription::query()->where('company_id', $company->id)->firstOrFail()];
    }

    public function test_pengingat_di_h7_h3_h1_h0_dan_lewat_tidak(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-18 09:00:00');

        [$owner, $company, $subscription] = $this->makeCompany('PT A', 'pt-a');
        $subscription->trial_ends_at = '2026-10-25';
        $subscription->save();

        $run = function (): void {
            $this->artisan('subscription:remind')->assertSuccessful();
        };
        $count = fn (): int => AppNotification::query()->where('type', 'langganan.jatuh_tempo')->count();

        $run();
        $this->assertDatabaseHas('notifications', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'type' => 'langganan.jatuh_tempo',
            'title' => 'Langganan berakhir dalam 7 hari',
            'link' => '/subscription',
        ]);
        Mail::assertSent(SubscriptionReminderMail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->daysLeft === 7 && ! $mail->expired);
        $this->assertSame(1, $count());

        // H-5: bukan kelipatan pengingat
        Carbon::setTestNow('2026-10-20 09:00:00');
        $run();
        $this->assertSame(1, $count());

        // H-3
        Carbon::setTestNow('2026-10-22 09:00:00');
        $run();
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'Langganan berakhir dalam 3 hari']);

        // H-2: tidak
        Carbon::setTestNow('2026-10-23 09:00:00');
        $run();
        $this->assertSame(2, $count());

        // H-1
        Carbon::setTestNow('2026-10-24 09:00:00');
        $run();
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'Langganan berakhir dalam 1 hari']);

        // H-0
        Carbon::setTestNow('2026-10-25 09:00:00');
        $run();
        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'title' => 'Langganan berakhir hari ini']);

        $this->assertSame(4, $count());
        Mail::assertSent(SubscriptionReminderMail::class, 4);
        $this->assertSame('trial', $subscription->refresh()->status);
    }

    public function test_status_aktif_pakai_ends_at(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-18 09:00:00');

        [$owner, $company, $subscription] = $this->makeCompany('PT B', 'pt-b');
        $subscription->status = 'aktif';
        $subscription->trial_ends_at = null;
        $subscription->ends_at = '2026-10-21';
        $subscription->save();

        $this->artisan('subscription:remind')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'title' => 'Langganan berakhir dalam 3 hari',
        ]);
        Mail::assertSent(SubscriptionReminderMail::class, fn ($mail) => $mail->expired === false);
    }

    public function test_langganan_lewat_jadi_read_only_dan_blokir_tulis(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-11-02 09:00:00');

        [$owner, $company, $subscription] = $this->makeCompany('PT A', 'pt-a');
        $subscription->trial_ends_at = '2026-11-01';
        $subscription->save();

        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        setPermissionsTeamId($company->id);
        $roleId = (int) Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web', 'company_id' => $company->id])->id;

        $payload = fn (string $email): array => [
            'name' => 'Karyawan '.$email,
            'email' => $email,
            'branch_id' => $branch->id,
            'role_id' => $roleId,
        ];

        // sebelum command: masih trial (H+... sudah lewat tapi belum disapu) → tulis belum diblokir
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/users", $payload('satu@example.com'))
            ->assertCreated();

        $this->artisan('subscription:remind')->assertSuccessful();

        $this->assertSame('read_only', $subscription->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'langganan.berakhir',
            'entity_id' => $subscription->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'title' => 'Langganan berakhir',
        ]);
        Mail::assertSent(SubscriptionReminderMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email) && $mail->expired === true;
        });

        // sesudah: tulis diblokir 403, baca tetap boleh
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/users", $payload('dua@example.com'))
            ->assertForbidden();
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/branches")
            ->assertOk();

        // dijalankan ulang: idempotent (tidak dobel notif/email)
        $this->artisan('subscription:remind')->assertSuccessful();
        Mail::assertSent(SubscriptionReminderMail::class, 1);
        $this->assertSame(1, AppNotification::query()->where('type', 'langganan.jatuh_tempo')->count());
    }
}
