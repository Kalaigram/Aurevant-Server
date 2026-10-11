<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Responses\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_mengembalikan_struktur_kontrak(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Berhasil');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'status' => 'success',
            'message' => 'Berhasil',
            'data' => ['id' => 1],
            'errors' => null,
        ], $response->getData(true));
    }

    public function test_error_mengembalikan_struktur_kontrak(): void
    {
        $response = ApiResponse::error('Validasi gagal', ['nama' => ['wajib diisi']], 422);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals([
            'status' => 'error',
            'message' => 'Validasi gagal',
            'data' => null,
            'errors' => ['nama' => ['wajib diisi']],
        ], $response->getData(true));
    }
}
