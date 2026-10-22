<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
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
