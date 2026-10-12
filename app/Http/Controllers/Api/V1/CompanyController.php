<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\UseCases\CreateCompany;
use App\Application\Company\UseCases\ListCompanies;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, CreateCompany $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:20'],
            'branch_name' => ['required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
        ]);

        return ApiResponse::success(
            $useCase(
                $request->user(),
                $validated['name'],
                $validated['branch_code'],
                $validated['branch_name'],
                $validated['timezone'] ?? 'Asia/Jakarta',
            ),
            'Perusahaan dibuat.',
            201
        );
    }
}
