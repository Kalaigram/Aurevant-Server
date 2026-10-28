<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Catalog\UseCases\CreateServicePackage;
use App\Application\Catalog\UseCases\DeleteServicePackage;
use App\Application\Catalog\UseCases\ListServicePackages;
use App\Application\Catalog\UseCases\UpdateServicePackage;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServicePackage;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServicePackageController extends Controller
{
    public function index(Request $request, Company $company, ListServicePackages $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $validated['q'] ?? null,
            ),
            'Daftar paket jasa.'
        );
    }

    public function store(Request $request, Company $company, CreateServicePackage $useCase, ItemRepositoryInterface $items): JsonResponse
    {
        [$attributes, $lines] = $this->validatedAttributes($request, $company, $items);

        return ApiResponse::success(
            $useCase($company, $attributes, $lines),
            'Paket jasa dibuat.',
            201
        );
    }

    public function update(Request $request, Company $company, int $package, ServicePackageRepositoryInterface $packages, ItemRepositoryInterface $items, UpdateServicePackage $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $package, $packages);
        [$attributes, $lines] = $this->validatedAttributes($request, $company, $items, $scoped->id);

        return ApiResponse::success(
            $useCase($scoped, $attributes, $lines),
            'Paket jasa diperbarui.'
        );
    }

    public function destroy(Company $company, int $package, ServicePackageRepositoryInterface $packages, DeleteServicePackage $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $package, $packages));

        return ApiResponse::success(null, 'Paket jasa dihapus.');
    }

    private function scoped(Company $company, int $id, ServicePackageRepositoryInterface $packages): ServicePackage
    {
        return $packages->findForCompany($company, $id) ?? abort(404);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array{item_id: int, qty: float}>}
     */
    private function validatedAttributes(Request $request, Company $company, ItemRepositoryInterface $items, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('service_packages', 'code')->where('company_id', $company->id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['aktif', 'nonaktif'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $lines = [];
        foreach ($validated['items'] as $line) {
            $item = $items->findForCompany($company, (int) $line['item_id']) ?? abort(404);

            if ($item->type !== 'jasa') {
                throw ValidationException::withMessages([
                    'items' => 'Item '.$item->code.' bukan jasa.',
                ]);
            }

            $lines[] = [
                'item_id' => $item->id,
                'qty' => (float) $line['qty'],
            ];
        }

        unset($validated['items']);
        if ($ignoreId === null) {
            $validated['status'] ??= 'aktif';
        }

        return [$validated, $lines];
    }
}
