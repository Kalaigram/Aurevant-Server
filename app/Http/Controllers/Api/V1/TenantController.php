<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Admin\UseCases\ManageTenants;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request, ManageTenants $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(ManageTenants::STATUSES)],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                (int) ($validated['per_page'] ?? 15),
                $validated['search'] ?? null,
                $validated['status'] ?? null,
            ),
            'Daftar perusahaan.'
        );
    }

    public function show(int $company, ManageTenants $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->show($company), 'Detail perusahaan.');
    }

    public function update(Request $request, Company $company, ManageTenants $useCase): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ManageTenants::STATUSES)],
        ]);

        return ApiResponse::success(
            $useCase->setStatus($company, $validated['status']),
            'Status perusahaan diperbarui.'
        );
    }
}
