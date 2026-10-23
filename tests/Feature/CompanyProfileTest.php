<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: Company} */
    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);

        return [$owner, $company];
    }

    public function test_profil_perusahaan_mengekspos_branding_dan_kontak(): void
    {
        [$owner, $company] = $this->setupWorld();
        $company->update([
            'legal_name' => 'PT Aurevant Jaya',
            'tax_id' => '01.234.567.8-901.000',
            'email' => 'halo@aurevant.id',
            'phone' => '0215550100',
            'address' => 'Jl. Melati No. 7',
            'logo_path' => 'company-logos/logo.png',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/profile")
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Profil perusahaan.',
                'data' => [
                    'id' => $company->id,
                    'name' => 'PT A',
                    'legalName' => 'PT Aurevant Jaya',
                    'taxId' => '01.234.567.8-901.000',
                    'email' => 'halo@aurevant.id',
                    'phone' => '0215550100',
                    'address' => 'Jl. Melati No. 7',
                    'logoPath' => 'company-logos/logo.png',
                    'logoUrl' => '/storage/company-logos/logo.png',
                    'slug' => 'pt-a',
                    'status' => 'aktif',
                ],
            ]);
    }

    public function test_pemilik_memperbarui_profil_dan_upload_logo(): void
    {
        [$owner, $company] = $this->setupWorld();
        Storage::fake('public');

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/companies/{$company->id}/profile", [
                'name' => 'Aurevant Retail',
                'legal_name' => 'PT Aurevant Retail',
                'tax_id' => '02.345.678.9-012.000',
                'email' => 'kontak@aurevant.id',
                'phone' => '0215550200',
                'address' => 'Jl. Kenanga No. 8',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Aurevant Retail')
            ->assertJsonPath('data.legalName', 'PT Aurevant Retail');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Aurevant Retail',
            'email' => 'kontak@aurevant.id',
        ]);

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/logo", ['logo' => $logo])
            ->assertOk();

        $path = (string) $response->json('data.logoPath');
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/companies/{$company->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.logoUrl', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'logo_path' => null]);
    }

    public function test_upload_logo_baru_menghapus_logo_lama(): void
    {
        [$owner, $company] = $this->setupWorld();
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/lama.png', 'lama');
        $company->update(['logo_path' => 'company-logos/lama.png']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/logo", [
                'logo' => UploadedFile::fake()->image('baru.jpg', 120, 120),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing('company-logos/lama.png');
    }

    public function test_anggota_tanpa_izin_tidak_bisa_mengubah_profil_atau_logo(): void
    {
        [, $company] = $this->setupWorld();
        $staff = User::factory()->create();

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/companies/{$company->id}/profile", ['name' => 'Nakal'])
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/logo", [
                'logo' => UploadedFile::fake()->image('nakal.png'),
            ])
            ->assertForbidden();
    }

    public function test_logo_harus_berupa_gambar_dengan_ukuran_wajar(): void
    {
        [$owner, $company] = $this->setupWorld();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/companies/{$company->id}/logo", [
                'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('logo');
    }
}
