<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_ditolak_dengan_401(): void
    {
        $this->getJson('/api/v1/companies')
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
                'data' => null,
            ]);
    }

    public function test_route_tidak_ada_mengikuti_kontrak_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tidak-ada')
            ->assertNotFound()
            ->assertJson([
                'status' => 'error',
                'message' => 'Tidak ditemukan.',
                'data' => null,
            ]);
    }

    public function test_daftar_company_mengikuti_kontrak_respons(): void
    {
        $user = User::factory()->create();
        Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);
        Company::create(['name' => 'Salon Ayu', 'slug' => 'salon-ayu', 'status' => 'trial']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies');

        $response->assertOk()->assertJson([
            'status' => 'success',
            'message' => 'Daftar perusahaan',
            'errors' => null,
        ]);

        $this->assertEquals(
            ['Bengkel Maju', 'Salon Ayu'],
            $response->json('data.*.name')
        );
    }

    public function test_paginasi_dibatasi_dan_ada_meta(): void
    {
        $user = User::factory()->create();
        Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'trial']);
        Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'trial']);
        Company::create(['name' => 'C', 'slug' => 'c', 'status' => 'trial']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies?per_page=2');

        $response->assertOk()->assertJson([
            'status' => 'success',
            'meta' => ['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2],
        ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_per_page_lewat_batas_ditolak_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies?per_page=999')
            ->assertUnprocessable()
            ->assertJson([
                'status' => 'error',
                'message' => 'Validasi gagal.',
            ])
            ->assertJsonValidationErrors('per_page');
    }

    public function test_api_dibatasi_rate_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60');
    }

    public function test_cors_hanya_mengizinkan_frontend_terdaftar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/v1/companies')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Origin' => 'https://situs-asing.test'])
            ->getJson('/api/v1/companies');

        $response->assertOk();
        $this->assertNotEquals(
            'https://situs-asing.test',
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }
}
