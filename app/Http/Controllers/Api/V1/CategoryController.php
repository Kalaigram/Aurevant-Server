<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\DTOs\CategoryDTO;
use App\Application\Catalog\UseCases\CreateCategory;
use App\Application\Catalog\UseCases\DeleteCategory;
use App\Application\Catalog\UseCases\ListCategories;
use App\Application\Catalog\UseCases\UpdateCategory;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request, Company $company, ListCategories $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar kategori.'
        );
    }

    public function store(Request $request, Company $company, CreateCategory $useCase): JsonResponse
    {
        $validated = $request->validate($this->rules($company));

        return ApiResponse::success(
            $useCase($company, $validated),
            'Kategori dibuat.',
            201
        );
    }

    public function show(Company $company, int $category, CategoryRepositoryInterface $categories): JsonResponse
    {
        return ApiResponse::success(
            CategoryDTO::fromModel($this->scoped($company, $category, $categories)),
            'Detail kategori.'
        );
    }

    public function update(Request $request, Company $company, int $category, CategoryRepositoryInterface $categories, UpdateCategory $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $category, $categories);
        $validated = $request->validate($this->rules($company, $scoped->id));

        return ApiResponse::success(
            $useCase($scoped, $validated),
            'Kategori diperbarui.'
        );
    }

    public function destroy(Company $company, int $category, CategoryRepositoryInterface $categories, DeleteCategory $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $category, $categories));

        return ApiResponse::success(null, 'Kategori dihapus.');
    }

    private function scoped(Company $company, int $id, CategoryRepositoryInterface $categories): Category
    {
        return $categories->findForCompany($company, $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function rules(Company $company, ?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories', 'name')
                    ->where('company_id', $company->id)
                    ->ignore($ignoreId),
            ],
        ];
    }
}
