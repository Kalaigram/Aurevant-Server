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
}
