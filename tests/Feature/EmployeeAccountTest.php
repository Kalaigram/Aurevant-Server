<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class EmployeeAccountTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    /** @return array{0: User, 1: Company, 2: Branch, 3: Role} */
    private function setupWorld(): array
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($owner, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);

        setPermissionsTeamId($company->id);
        $role = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web', 'company_id' => $company->id]);

        return [$owner, $company, $branch, $role];
    }

    /** @return array{0: int, 1: string} id + password awal karyawan baru */
    private function createEmployee(User $owner, Company $company, Branch $branch, Role $role): array
    {
        $response = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users",
            [
                'name' => 'Siti Kasir',
                'email' => 'siti@example.com',
                'phone' => '081299998888',
                'branch_id' => $branch->id,
                'role_id' => $role->id,
                'ptkp_status' => 'TK/0',
            ]
        )->assertCreated();

        return [(int) $response->json('data.user.id'), (string) $response->json('data.initialPassword')];
    }

    public function test_owner_membuat_akun_karyawan_dan_karyawan_bisa_login(): void
    {
        [$owner, $company, $branch, $role] = $this->setupWorld();

        [$staffId, $initialPassword] = $this->createEmployee($owner, $company, $branch, $role);
        $this->assertNotEmpty($initialPassword);

        $this->assertDatabaseHas('employee_assignments', [
            'company_id' => $company->id,
            'user_id' => $staffId,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'karyawan.buat',
            'entity_id' => $staffId,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'siti@example.com',
            'password' => $initialPassword,
        ])->assertOk();
    }

    public function test_buat_karyawan_duplikat_dan_izin(): void
    {
        [$owner, $company, $branch, $role] = $this->setupWorld();
        $this->createEmployee($owner, $company, $branch, $role);

        // email duplikat 422
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users",
            ['name' => 'Lain', 'email' => 'siti@example.com']
        )->assertUnprocessable()->assertJsonValidationErrors('email');

        // tanpa izin karyawan.kelola: 403
        $staff = User::factory()->create();
        $this->actingAs($staff, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users",
            ['name' => 'Nakal', 'email' => 'nakal@example.com']
        )->assertForbidden();

        // branch/role bukan milik perusahaan: 404
        $companyLain = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $branchLain = Branch::create(['company_id' => $companyLain->id, 'code' => 'XX', 'name' => 'Lain']);
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users",
            ['name' => 'Salah Cabang', 'email' => 'salah@example.com', 'branch_id' => $branchLain->id]
        )->assertNotFound();
    }

    public function test_reset_password_karyawan_oleh_hr(): void
    {
        [$owner, $company, $branch, $role] = $this->setupWorld();
        [$staffId, $initialPassword] = $this->createEmployee($owner, $company, $branch, $role);

        // karyawan login dulu → punya sesi aktif
        $staffToken = $this->postJson('/api/v1/auth/login', [
            'login' => 'siti@example.com',
            'password' => $initialPassword,
        ])->assertOk()->json('data.token');

        $newPassword = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users/{$staffId}/reset-password",
            ['password' => 'gantitotal123']
        )->assertOk()->json('data.initialPassword');
        $this->assertSame('gantitotal123', $newPassword);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'karyawan.reset_password',
            'entity_id' => $staffId,
        ]);

        // sesi lama karyawan dicabut
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$staffToken])->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        // login dengan password baru
        $this->postJson('/api/v1/auth/login', [
            'login' => 'siti@example.com',
            'password' => 'gantitotal123',
        ])->assertOk();

        // user bukan anggota perusahaan: 404
        $outsider = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/companies/{$company->id}/users/{$outsider->id}/reset-password",
            []
        )->assertNotFound();
    }
}
