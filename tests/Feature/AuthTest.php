<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_berhasil_mengembalikan_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $response->assertCreated()->assertJson([
            'status' => 'success',
            'message' => 'Pendaftaran berhasil.',
            'data' => [
                'user' => ['name' => 'Budi', 'email' => 'budi@example.com'],
            ],
        ]);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
    }

    public function test_register_duplikat_ditolak_422(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_pakai_email_dan_no_hp(): void
    {
        User::factory()->create([
            'email' => 'budi@example.com',
            'phone' => '081234567890',
            'password' => 'rahasia123',
        ]);

        foreach (['budi@example.com', '081234567890'] as $identifier) {
            $this->postJson('/api/v1/auth/login', [
                'login' => $identifier,
                'password' => 'rahasia123',
            ])->assertOk()->assertJsonPath('status', 'success');
        }
    }

    public function test_login_salah_mengikuti_kontrak_401(): void
    {
        User::factory()->create(['email' => 'budi@example.com', 'password' => 'rahasia123']);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'budi@example.com',
            'password' => 'salah-salah',
        ])->assertUnauthorized()->assertJson([
            'status' => 'error',
            'message' => 'Email/no. HP atau password salah.',
            'data' => null,
        ]);
    }

    public function test_me_butuh_token_dan_logout_mencabut_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);
        $userId = $register->json('data.user.id');
        $token = $register->json('data.token');

        $auth = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($auth)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'budi@example.com');

        $this->withHeaders($auth)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $userId,
            'tokenable_type' => User::class,
        ]);
    }
}
