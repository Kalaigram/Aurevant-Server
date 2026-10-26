<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private function assign(User $user, Company $company): void
    {
        EmployeeAssignment::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'started_at' => now()->toDateString(),
        ]);
    }

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

    public function test_daftar_company_hanya_yang_punya_penempatan_aktif(): void
    {
        $user = User::factory()->create();
        $bengkel = Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);
        Company::create(['name' => 'Salon Ayu', 'slug' => 'salon-ayu', 'status' => 'trial']);
        $this->assign($user, $bengkel);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies');

        $response->assertOk()->assertJson([
            'status' => 'success',
            'message' => 'Daftar perusahaan',
            'errors' => null,
        ]);

        $this->assertSame(['Bengkel Maju'], $response->json('data.*.name'));
    }

    public function test_daftar_company_kosong_tanpa_penempatan(): void
    {
        $user = User::factory()->create();
        Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_profil_company_menolak_tanpa_penempatan_aktif(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/profile")
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => 'Akses ditolak.',
                'data' => null,
            ]);
    }

    public function test_daftar_company_menolak_penempatan_yang_sudah_berakhir(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);
        EmployeeAssignment::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'started_at' => now()->subMonth()->toDateString(),
            'ended_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_paginasi_dibatasi_dan_ada_meta(): void
    {
        $user = User::factory()->create();

        foreach (['A', 'B', 'C'] as $name) {
            $company = Company::create(['name' => $name, 'slug' => strtolower($name), 'status' => 'trial']);
            $this->assign($user, $company);
        }

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
