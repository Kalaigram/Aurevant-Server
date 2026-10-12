<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithPermissions;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;
    use WithPermissions;

    private function auth(): User
    {
        return User::factory()->create();
    }

    public function test_guest_ditolak(): void
    {
        $company = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);

        $this->getJson("/api/v1/companies/{$company->id}/categories")->assertUnauthorized();
    }

    public function test_crud_kategori_scoped_per_company(): void
    {
        $user = $this->auth();
        $a = Company::create(['name' => 'A', 'slug' => 'a', 'status' => 'aktif']);
        $b = Company::create(['name' => 'B', 'slug' => 'b', 'status' => 'aktif']);
        $this->owner($user, $a);
        $this->owner($user, $b);
        Category::create(['company_id' => $b->id, 'name' => 'Milik B']);
        $api = fn () => $this->actingAs($user, 'sanctum');

        // index hanya milik A
        $id = $api()->postJson("/api/v1/companies/{$a->id}/categories", ['name' => 'Sparepart'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sparepart')
            ->json('data.id');

        $api()->getJson("/api/v1/companies/{$a->id}/categories")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // nama sama di company sama: tolak; di company beda: boleh
        $api()->postJson("/api/v1/companies/{$a->id}/categories", ['name' => 'Sparepart'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $api()->postJson("/api/v1/companies/{$b->id}/categories", ['name' => 'Sparepart'])
            ->assertCreated();

        // show lintas company: 404
        $milikB = Category::where('company_id', $b->id)->where('name', 'Milik B')->firstOrFail();
        $api()->getJson("/api/v1/companies/{$a->id}/categories/{$milikB->id}")
            ->assertNotFound()->assertJsonPath('status', 'error');

        // update + hapus
        $api()->putJson("/api/v1/companies/{$a->id}/categories/{$id}", ['name' => 'Sparepart Motor'])
            ->assertOk()->assertJsonPath('data.name', 'Sparepart Motor');
        $api()->deleteJson("/api/v1/companies/{$a->id}/categories/{$id}")
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertSoftDeleted('categories', ['id' => $id]);
    }
}
