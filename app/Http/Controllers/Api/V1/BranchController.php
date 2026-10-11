<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Branch\DTOs\BranchDTO;
use App\Application\Branch\UseCases\CreateBranch;
use App\Application\Branch\UseCases\DeleteBranch;
use App\Application\Branch\UseCases\ListBranches;
use App\Application\Branch\UseCases\UpdateBranch;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request, Company $company, ListBranches $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar cabang.'
        );
    }

    public function store(Request $request, Company $company, CreateBranch $useCase): JsonResponse
    {
        $validated = $request->validate($this->rules($company));

        return ApiResponse::success(
            $useCase($company, $validated),
            'Cabang dibuat.',
            201
        );
    }

    public function show(Company $company, int $branch, BranchRepositoryInterface $branches): JsonResponse
    {
        return ApiResponse::success(
            BranchDTO::fromModel($this->scoped($company, $branch, $branches)),
            'Detail cabang.'
        );
    }

    public function update(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, UpdateBranch $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $branch, $branches);
        $validated = $request->validate($this->rules($company, $scoped->id));

        return ApiResponse::success(
            $useCase($scoped, $validated),
            'Cabang diperbarui.'
        );
    }

    public function destroy(Company $company, int $branch, BranchRepositoryInterface $branches, DeleteBranch $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $branch, $branches));

        return ApiResponse::success(null, 'Cabang dihapus.');
    }

    private function scoped(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function rules(Company $company, ?int $ignoreId = null): array
    {
        $uniqueCode = Rule::unique('branches', 'code')
            ->where('company_id', $company->id)
            ->ignore($ignoreId);

        return [
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['pusat', 'cabang', 'gudang'])],
            'timezone' => ['sometimes', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'string', Rule::in(['aktif', 'nonaktif'])],
        ];
    }
}
