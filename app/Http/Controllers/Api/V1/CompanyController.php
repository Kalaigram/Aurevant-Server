<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Company\UseCases\ListCompanies;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request, ListCompanies $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->paginated((int) ($validated['per_page'] ?? 15)),
            'Daftar perusahaan'
        );
    }
}
