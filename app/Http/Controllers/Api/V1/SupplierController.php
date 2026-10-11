<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Purchase\DTOs\SupplierDTO;
use App\Application\Purchase\UseCases\CreateSupplier;
use App\Application\Purchase\UseCases\DeleteSupplier;
use App\Application\Purchase\UseCases\ListSuppliers;
use App\Application\Purchase\UseCases\UpdateSupplier;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Supplier;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request, Company $company, ListSuppliers $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar supplier.'
        );
    }

    public function store(Request $request, Company $company, CreateSupplier $useCase): JsonResponse
    {
        $validated = $request->validate($this->rules($company));

        return ApiResponse::success(
            $useCase($company, $validated),
            'Supplier dibuat.',
            201
        );
    }

    public function show(Company $company, int $supplier, SupplierRepositoryInterface $suppliers): JsonResponse
    {
        return ApiResponse::success(
            SupplierDTO::fromModel($this->scoped($company, $supplier, $suppliers)),
            'Detail supplier.'
        );
    }

    public function update(Request $request, Company $company, int $supplier, SupplierRepositoryInterface $suppliers, UpdateSupplier $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $supplier, $suppliers);
        $validated = $request->validate($this->rules($company, $scoped->id));

        return ApiResponse::success(
            $useCase($scoped, $validated),
            'Supplier diperbarui.'
        );
    }

    public function destroy(Company $company, int $supplier, SupplierRepositoryInterface $suppliers, DeleteSupplier $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $supplier, $suppliers));

        return ApiResponse::success(null, 'Supplier dihapus.');
    }

    private function scoped(Company $company, int $id, SupplierRepositoryInterface $suppliers): Supplier
    {
        return $suppliers->findForCompany($company, $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function rules(Company $company, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('suppliers', 'name')->where('company_id', $company->id)->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
