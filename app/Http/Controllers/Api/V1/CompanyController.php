<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\UseCases\CreateCompany;
use App\Application\Company\DTOs\CompanyDTO;
use App\Application\Company\UseCases\DeleteCompanyLogo;
use App\Application\Company\UseCases\ListCompanies;
use App\Application\Company\UseCases\StoreCompanyLogo;
use App\Application\Company\UseCases\UpdateCompanyProfile;
use App\Http\Controllers\Controller;
use App\Models\Company;
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

    public function show(Company $company): JsonResponse
    {
        return ApiResponse::success(CompanyDTO::fromModel($company), 'Profil perusahaan.');
    }

    public function update(Request $request, Company $company, UpdateCompanyProfile $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        return ApiResponse::success($useCase($company, $validated), 'Profil perusahaan diperbarui.');
    }

    public function uploadLogo(Request $request, Company $company, StoreCompanyLogo $useCase): JsonResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return ApiResponse::success($useCase($company, $validated['logo']), 'Logo perusahaan diperbarui.');
    }

    public function deleteLogo(Company $company, DeleteCompanyLogo $useCase): JsonResponse
    {
        return ApiResponse::success($useCase($company), 'Logo perusahaan dihapus.');
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
