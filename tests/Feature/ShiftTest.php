<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_buka_tutup_shift_dan_selisih(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $base = "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts";

        $this->postJson($base, ['opening_cash' => 100000])->assertUnauthorized();

        $id = $api()->postJson($base, ['opening_cash' => 100000])
            ->assertCreated()->assertJsonPath('data.status', 'terbuka')->json('data.id');

        // shift ganda: tolak
        $api()->postJson($base, ['opening_cash' => 50000])
            ->assertUnprocessable()->assertJsonPath('status', 'error');

        $api()->getJson("{$base}/{$id}")->assertOk()->assertJsonPath('data.openingCash', '100000.00');

        // tutup: fisik 95000 vs sistem 100000 → selisih -5000
        $api()->postJson("{$base}/{$id}/close", ['closing_cash_counted' => 95000])
            ->assertOk()
            ->assertJsonPath('data.status', 'ditutup')
            ->assertJsonPath('data.difference', '-5000.00');

        // tutup dua kali: tolak, lalu boleh buka lagi
        $api()->postJson("{$base}/{$id}/close", ['closing_cash_counted' => 95000])
            ->assertUnprocessable();
        $api()->postJson($base, ['opening_cash' => 95000])->assertCreated();
    }

    public function test_tutup_menghitung_uang_tunai_penjualan(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $shiftId = $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 100000]
        )->json('data.id');

        // simulasi penjualan tunai 50000 (dibuat langsung, endpoint nota menyusul)
        Sale::create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'shift_id' => $shiftId,
            'user_id' => $user->id, 'number' => 'TKO-TEST-0001', 'status' => 'lunas',
            'subtotal' => 50000, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 50000,
        ])->payments()->create(['method' => 'tunai', 'amount' => 50000]);

        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts/{$shiftId}/close",
            ['closing_cash_counted' => 150000]
        )->assertOk()->assertJsonPath('data.difference', '0.00');
    }
}
