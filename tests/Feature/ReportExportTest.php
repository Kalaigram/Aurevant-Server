<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_ekspor_laba_rugi_excel_dan_pdf(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);

        $today = now()->toDateString();
        $base = "/api/v1/companies/{$company->id}/reports/profit-loss/export";
        $api = fn () => $this->actingAs($user, 'sanctum');

        $excel = $api()->get("{$base}?from={$today}&to={$today}&format=xlsx");
        $excel->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $excel->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'report-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $excel->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('PT A', $sheet->getCell('A1')->getValue());
        $this->assertSame('Laporan laba rugi', $sheet->getCell('A2')->getValue());
        $this->assertSame('Periode '.now()->format('d/m/Y'), $sheet->getCell('A3')->getValue());
        $spreadsheet->disconnectWorksheets();
        unlink($path);

        $pdf = $api()->get("{$base}?from={$today}&to={$today}&format=pdf");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', (string) $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }

    public function test_semua_jenis_laporan_bisa_diekspor(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        $today = now()->toDateString();
        $base = "/api/v1/companies/{$company->id}/reports";

        foreach (['daily', 'cash-flow', 'balance-sheet', 'trial-balance', 'consolidation'] as $report) {
            $response = $this->actingAs($user, 'sanctum')
                ->get("{$base}/{$report}/export?from={$today}&to={$today}&format=xlsx");

            $response->assertOk();
            $this->assertStringStartsWith('PK', $response->streamedContent());
        }

        $this->actingAs($user, 'sanctum')
            ->get("{$base}/daily/export?from={$today}&to={$today}&format=xlsx&branch_id={$branch->id}")
            ->assertOk()
            ->assertDownload("laporan-harian-{$today}_{$today}-cabang-{$branch->id}.xlsx");
    }

    public function test_ekspor_butuh_izin_keuangan(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->member($user, $company);
        app(SubscriptionRepositoryInterface::class)->startTrial($company);

        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/reports/profit-loss/export?from={$today}&to={$today}&format=xlsx")
            ->assertForbidden();
    }

    public function test_ekspor_konsolidasi_butuh_izin_konsolidasi(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);

        setPermissionsTeamId($company->id);
        $role = Role::query()
            ->where('name', 'Pemilik')
            ->where('company_id', $company->id)
            ->firstOrFail();
        $role->revokePermissionTo('laporan.konsolidasi');

        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/reports/consolidation/export?from={$today}&to={$today}&format=xlsx")
            ->assertForbidden();
    }

    public function test_ekspor_validasi_format_laporan_dan_cabang(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $other = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $otherBranch = Branch::create(['company_id' => $other->id, 'code' => 'B', 'name' => 'Cabang B']);

        $today = now()->toDateString();
        $base = "/api/v1/companies/{$company->id}/reports";
        $api = fn () => $this->actingAs($user, 'sanctum');

        $api()->getJson("{$base}/profit-loss/export?from={$today}&to={$today}&format=csv")
            ->assertStatus(422);

        $api()->getJson("{$base}/unknown/export?from={$today}&to={$today}&format=xlsx")
            ->assertNotFound();

        $api()->getJson("{$base}/daily/export?from={$today}&to={$today}&format=xlsx&branch_id={$otherBranch->id}")
            ->assertNotFound();
    }
}
