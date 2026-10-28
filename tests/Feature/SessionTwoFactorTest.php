<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dan_cabut_sesi_perangkat(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);
        $current = $user->createToken('Web')->plainTextToken;
        $other = $user->createToken('Android');
        $headers = ['Authorization' => 'Bearer '.$current];

        $sessions = $this->withHeaders($headers)->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $sessions);
        $this->assertTrue(collect($sessions)->firstWhere('isCurrent', true)['isCurrent']);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/auth/sessions/'.$other->accessToken->id)
            ->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);

        $currentId = $user->tokens()->where('name', 'Web')->firstOrFail()->id;
        $this->withHeaders($headers)
            ->deleteJson('/api/v1/auth/sessions/'.$currentId)
            ->assertUnprocessable();

        $user->createToken('Tablet');
        $this->withHeaders($headers)->deleteJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.revoked', 1);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_aktifkan_login_dan_nonaktifkan_2fa(): void
    {
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
        ]);
        $token = $user->createToken('Web')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)->getJson('/api/v1/auth/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $setup = $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/setup')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauthUri', 'recoveryCodes']])
            ->json('data');
        $this->assertCount(8, $setup['recoveryCodes']);

        $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/enable', [
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/enable', [
            'code' => Totp::code($setup['secret']),
        ])->assertOk()->assertJsonPath('data.enabled', true);

        $this->withHeaders($headers)->getJson('/api/v1/auth/two-factor')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.pending', false);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'budi@example.com',
            'password' => 'rahasia123',
        ])->assertUnprocessable()->assertJsonValidationErrors('two_factor_code');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'budi@example.com',
            'password' => 'rahasia123',
            'two_factor_code' => Totp::code($setup['secret']),
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'budi@example.com',
            'password' => 'rahasia123',
            'two_factor_code' => $setup['recoveryCodes'][0],
        ])->assertOk();
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/disable', [
            'password' => 'salah-salah',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->withHeaders($headers)->postJson('/api/v1/auth/two-factor/disable', [
            'password' => 'rahasia123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'budi@example.com',
            'password' => 'rahasia123',
        ])->assertOk();
    }
}
