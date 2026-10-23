<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_profil_dapat_diubah_dan_mengekspos_field_tersisa(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Lama',
            'phone' => '081200000001',
            'ptkp_status' => 'K/1',
            'avatar_path' => 'user-avatars/budi.png',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Budi Baru',
                'phone' => '081200000002',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Profil diperbarui.')
            ->assertJsonPath('data.name', 'Budi Baru')
            ->assertJsonPath('data.phone', '081200000002');

        $this->assertSame('K/1', $response->json('data.ptkpStatus'));
        $this->assertNotEmpty($response->json('data.employeeUuid'));
        $this->assertSame('/storage/user-avatars/budi.png', $response->json('data.avatarUrl'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Budi Baru',
            'phone' => '081200000002',
        ]);
    }

    public function test_nomor_hp_profil_tidak_boleh_duplikat(): void
    {
        User::factory()->create(['phone' => '081200000009']);
        $user = User::factory()->create(['phone' => '081200000001']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/auth/profile', [
                'name' => $user->name,
                'phone' => '081200000009',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_upload_dan_hapus_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/avatar', [
                'avatar' => UploadedFile::fake()->image('foto.png', 160, 160),
            ])
            ->assertOk();

        $path = (string) $response->json('data.avatarPath');
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/auth/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatarUrl', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_baru_menghapus_file_lama(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'user-avatars/lama.png']);
        Storage::disk('public')->put('user-avatars/lama.png', 'lama');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/avatar', [
                'avatar' => UploadedFile::fake()->image('baru.png', 120, 120),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing('user-avatars/lama.png');
    }

    public function test_avatar_harus_berupa_gambar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/avatar', [
                'avatar' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }
}
