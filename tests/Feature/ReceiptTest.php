<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function jual(): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'PT A', 'slug' => 'pt-a', 'status' => 'aktif']);
        $this->owner($user, $company);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $oli = Item::create([
            'company_id' => $company->id, 'code' => 'OLI-01', 'name' => 'Oli',
            'type' => 'barang', 'unit' => 'pcs',
        ]);
        ItemPrice::create(['item_id' => $oli->id, 'branch_id' => $branch->id, 'price' => 50000]);
        $api = fn () => $this->actingAs($user, 'sanctum');
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/stock/movements",
            ['item_id' => $oli->id, 'type' => 'pembelian', 'qty' => 10, 'unit_cost' => 40000]
        )->assertCreated();
        $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/shifts",
            ['opening_cash' => 100000]
        )->assertCreated();
        $saleId = $api()->postJson(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales",
            [
                'idempotency_key' => 'struk-1',
                'items' => [['item_id' => $oli->id, 'qty' => 2]],
                'payments' => [['method' => 'tunai', 'amount' => 100000, 'tendered' => 100000]],
            ]
        )->assertCreated()->json('data.id');

        return [$user, $company, $branch, $saleId];
    }

    public function test_struk_pdf_terbit_untuk_nota_sendiri(): void
    {
        [$user, $company, $branch, $saleId] = $this->jual();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/{$saleId}/receipt")
            ->assertUnauthorized();

        $res = $this->actingAs($user, 'sanctum')->get(
            "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/{$saleId}/receipt"
        );
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->streamedContent());
    }

    public function test_struk_pdf_ditolak_tanpa_izin_dan_lintas_tenant(): void
    {
        [$user, $company, $branch, $saleId] = $this->jual();
        $url = "/api/v1/companies/{$company->id}/branches/{$branch->id}/sales/{$saleId}/receipt";

        $tanpaIzin = User::factory()->create();
        $this->actingAs($tanpaIzin, 'sanctum')->get($url)->assertForbidden();

        $lain = User::factory()->create();
        $b = Company::create(['name' => 'PT B', 'slug' => 'pt-b', 'status' => 'aktif']);
        $this->owner($lain, $b);
        $this->actingAs($lain, 'sanctum')->get($url)->assertForbidden();
    }
}
