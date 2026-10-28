<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_crud_pelanggan_scoped_per_company(): void
    {
        $user = User::factory()->create();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        Customer::create(['company_id' => $b->id, 'name' => 'Milik B', 'phone' => '0800']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $this->getJson("/api/v1/companies/{$a->id}/customers")->assertUnauthorized();

        $id = $api()->postJson("/api/v1/companies/{$a->id}/customers", [
            'name' => 'Andi', 'phone' => '08111',
        ])->assertCreated()->assertJsonPath('data.name', 'Andi')->json('data.id');

        $api()->getJson("/api/v1/companies/{$a->id}/customers?q=andi")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Andi');
        $api()->getJson("/api/v1/companies/{$a->id}/customers?q=08111")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // tanpa no HP boleh ganda
        $api()->postJson("/api/v1/companies/{$a->id}/customers", ['name' => 'Walk-in 1'])
            ->assertCreated();
        $api()->postJson("/api/v1/companies/{$a->id}/customers", ['name' => 'Walk-in 2'])
            ->assertCreated();

        // no sama satu company: tolak; beda company: boleh
        $api()->postJson("/api/v1/companies/{$a->id}/customers", ['name' => 'Kembar', 'phone' => '08111'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $api()->postJson("/api/v1/companies/{$b->id}/customers", ['name' => 'Cabang B', 'phone' => '08111'])
            ->assertCreated();

        $milikB = Customer::where('company_id', $b->id)->where('name', 'Milik B')->firstOrFail();
        $api()->getJson("/api/v1/companies/{$a->id}/customers/{$milikB->id}")
            ->assertNotFound()->assertJsonPath('status', 'error');

        $api()->putJson("/api/v1/companies/{$a->id}/customers/{$id}", ['name' => 'Andi Pratama'])
            ->assertOk()->assertJsonPath('data.name', 'Andi Pratama');
        $api()->deleteJson("/api/v1/companies/{$a->id}/customers/{$id}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $id]);
    }

    public function test_profil_pelanggan_memuat_riwayat_piutang_dan_order(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $other = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $company);
        $this->owner($user, $other);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'TKO', 'name' => 'Toko']);
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Andi',
            'phone' => '08111',
            'notes' => 'Langganan servis',
        ]);
        $shift = CashShift::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'terbuka',
            'opening_cash' => 0,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'number' => 'TKO-001',
            'status' => 'lunas',
            'subtotal' => 200000,
            'grand_total' => 200000,
        ]);
        SaleReturn::create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'kind' => 'retur',
            'reason' => 'Sebagian',
            'refund_method' => 'tunai',
            'refund_amount' => 50000,
            'status' => 'disetujui',
        ]);
        Receivable::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'amount' => 75000,
            'paid_amount' => 25000,
            'due_at' => now()->addWeek()->toDateString(),
        ]);
        ServiceOrder::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => 'diproses',
            'deposit_amount' => 10000,
        ]);
        ServiceOrder::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'status' => 'dibatalkan',
            'deposit_amount' => 0,
        ]);

        $data = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/customers/{$customer->id}/profile")
            ->assertOk()
            ->json('data');

        $this->assertSame('Andi', $data['customer']['name']);
        $this->assertSame(1, $data['stats']['transactions']);
        $this->assertEquals(200000, $data['stats']['grossSpend']);
        $this->assertEquals(50000, $data['stats']['refunds']);
        $this->assertEquals(150000, $data['stats']['netSpend']);
        $this->assertEquals(150000, $data['stats']['avgTicket']);
        $this->assertEquals(50000, $data['stats']['receivablesOutstanding']);
        $this->assertSame(2, $data['stats']['serviceOrders']);
        $this->assertSame(1, $data['stats']['activeServiceOrders']);
        $this->assertSame('TKO-001', $data['sales'][0]['number']);
        $this->assertEquals(50000, $data['sales'][0]['refund']);
        $this->assertEquals(150000, $data['sales'][0]['net']);
        $this->assertEquals(50000, $data['receivables'][0]['remaining']);

        $bukanAnggota = User::factory()->create();
        $this->actingAs($bukanAnggota, 'sanctum')
            ->getJson("/api/v1/companies/{$company->id}/customers/{$customer->id}/profile")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/companies/{$other->id}/customers/{$customer->id}/profile")
            ->assertNotFound();
    }

    public function test_ekspor_pelanggan_csv_dijaga_izin(): void
    {
        $user = User::factory()->create();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $this->owner($user, $a);
        Customer::create(['company_id' => $a->id, 'name' => 'Andi', 'phone' => '08111', 'address' => 'Jl. Mawar']);
        $url = "/api/v1/companies/{$a->id}/exports/customers";

        $this->getJson($url)->assertUnauthorized();

        $tanpaIzin = User::factory()->create();
        $this->actingAs($tanpaIzin, 'sanctum')->getJson($url)->assertForbidden();

        $res = $this->actingAs($user, 'sanctum')->get($url)->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $isi = $res->streamedContent();
        $this->assertStringContainsString('name,phone,address', $isi);
        $this->assertStringContainsString('Andi', $isi);
    }
}
