<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Application\Catalog\DTOs\ItemPriceDTO;
use App\Application\Catalog\UseCases\CreateItem;
use App\Application\Catalog\UseCases\DeleteItem;
use App\Application\Catalog\UseCases\DeleteItemImage;
use App\Application\Catalog\UseCases\ListItems;
use App\Application\Catalog\UseCases\SetItemPrice;
use App\Application\Catalog\UseCases\StoreItemImage;
use App\Application\Catalog\UseCases\UpdateItem;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function index(Request $request, Company $company, ListItems $useCase, CategoryRepositoryInterface $categories): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', 'string', Rule::in(['barang', 'jasa'])],
            'category_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $categoryId = isset($validated['category_id'])
            ? ($categories->findForCompany($company, (int) $validated['category_id'])?->id ?? abort(404))
            : null;

        return ApiResponse::paginated(
            $useCase(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $validated['type'] ?? null,
                $categoryId,
                $validated['q'] ?? null,
            ),
            'Daftar item.'
        );
    }

    public function store(Request $request, Company $company, CreateItem $useCase, CategoryRepositoryInterface $categories): JsonResponse
    {
        $validated = $this->validatedAttributes($request, $company, $categories);

        return ApiResponse::success(
            $useCase($company, $validated),
            'Item dibuat.',
            201
        );
    }

    public function show(Company $company, int $item, ItemRepositoryInterface $items): JsonResponse
    {
        return ApiResponse::success(
            ItemDTO::fromModel($this->scoped($company, $item, $items)),
            'Detail item.'
        );
    }

    public function update(Request $request, Company $company, int $item, ItemRepositoryInterface $items, CategoryRepositoryInterface $categories, UpdateItem $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $item, $items);
        $validated = $this->validatedAttributes($request, $company, $categories, $scoped->id);

        return ApiResponse::success(
            $useCase($scoped, $validated),
            'Item diperbarui.'
        );
    }

    public function destroy(Company $company, int $item, ItemRepositoryInterface $items, DeleteItem $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $item, $items));

        return ApiResponse::success(null, 'Item dihapus.');
    }

    public function uploadImage(Request $request, Company $company, int $item, ItemRepositoryInterface $items, StoreItemImage $useCase): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return ApiResponse::success(
            $useCase($this->scoped($company, $item, $items), $validated['image']),
            'Gambar item diperbarui.'
        );
    }

    public function deleteImage(Company $company, int $item, ItemRepositoryInterface $items, DeleteItemImage $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase($this->scoped($company, $item, $items)),
            'Gambar item dihapus.'
        );
    }

    public function price(Company $company, int $item, int $branch, ItemRepositoryInterface $items, BranchRepositoryInterface $branches, ItemPriceRepositoryInterface $prices): JsonResponse
    {
        $scopedItem = $this->scoped($company, $item, $items);
        $scopedBranch = $branches->findForCompany($company, $branch) ?? abort(404);
        $priceRow = $prices->findForItemBranch($scopedItem->id, $scopedBranch->id) ?? abort(404);

        return ApiResponse::success(
            ItemPriceDTO::fromModel($priceRow),
            'Harga cabang.'
        );
    }

    public function setPrice(Request $request, Company $company, int $item, int $branch, ItemRepositoryInterface $items, BranchRepositoryInterface $branches, SetItemPrice $useCase): JsonResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $scopedItem = $this->scoped($company, $item, $items);
        $scopedBranch = $branches->findForCompany($company, $branch) ?? abort(404);

        return ApiResponse::success(
            $useCase($scopedItem, $scopedBranch, (string) $validated['price']),
            'Harga cabang disimpan.'
        );
    }

    private function scoped(Company $company, int $id, ItemRepositoryInterface $items): Item
    {
        return $items->findForCompany($company, $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request, Company $company, CategoryRepositoryInterface $categories, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('items', 'code')->where('company_id', $company->id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['barang', 'jasa'])],
            'unit' => ['required_if:type,barang', 'nullable', 'string', 'max:20'],
            'track_stock' => ['sometimes', 'boolean'],
            'has_variants' => ['sometimes', 'boolean'],
            'track_batch' => ['sometimes', 'boolean'],
            'track_serial' => ['sometimes', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'commission_type' => ['nullable', 'string', Rule::in(['persen', 'nominal'])],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['aktif', 'nonaktif'])],
        ]);

        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            $categories->findForCompany($company, (int) $validated['category_id']) ?? abort(404);
        }

        if (
            (array_key_exists('commission_type', $validated) && $validated['commission_type'] === null)
            || (array_key_exists('commission_value', $validated) && $validated['commission_value'] === null)
        ) {
            $validated['commission_value'] = 0;
        }

        return $validated;
    }
}
