<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    public function test_crud_supplier_scoped_per_company(): void
    {
        $user = User::factory()->create();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        Supplier::create(['company_id' => $b->id, 'name' => 'Milik B']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        $this->getJson("/api/v1/companies/{$a->id}/suppliers")->assertUnauthorized();

        $id = $api()->postJson("/api/v1/companies/{$a->id}/suppliers", [
            'name' => 'PT Sumber Makmur', 'phone' => '031-111',
        ])->assertCreated()->assertJsonPath('data.name', 'PT Sumber Makmur')->json('data.id');

        $api()->getJson("/api/v1/companies/{$a->id}/suppliers")
            ->assertOk()->assertJsonPath('meta.total', 1);

        $api()->postJson("/api/v1/companies/{$a->id}/suppliers", ['name' => 'PT Sumber Makmur'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $api()->postJson("/api/v1/companies/{$b->id}/suppliers", ['name' => 'PT Sumber Makmur'])
            ->assertCreated();

        $milikB = Supplier::where('company_id', $b->id)->where('name', 'Milik B')->firstOrFail();
        $api()->getJson("/api/v1/companies/{$a->id}/suppliers/{$milikB->id}")
            ->assertNotFound()->assertJsonPath('status', 'error');

        $api()->putJson("/api/v1/companies/{$a->id}/suppliers/{$id}", ['name' => 'PT Sumber Jaya'])
            ->assertOk()->assertJsonPath('data.name', 'PT Sumber Jaya');
        $api()->deleteJson("/api/v1/companies/{$a->id}/suppliers/{$id}")->assertOk();
        $this->assertSoftDeleted('suppliers', ['id' => $id]);
    }
}
