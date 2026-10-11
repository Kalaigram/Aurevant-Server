<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_company_mengikuti_kontrak_respons(): void
    {
        Company::create(['name' => 'Bengkel Maju', 'slug' => 'bengkel-maju', 'status' => 'aktif']);
        Company::create(['name' => 'Salon Ayu', 'slug' => 'salon-ayu', 'status' => 'trial']);

        $response = $this->getJson('/api/v1/companies');

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
