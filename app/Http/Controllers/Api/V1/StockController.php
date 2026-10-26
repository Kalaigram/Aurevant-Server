<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\Audit;
use App\Application\Stock\UseCases\ListStockBalances;
use App\Application\Stock\UseCases\ListStockMovements;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Application\Stock\UseCases\SetStockMinimum;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, ListStockBalances $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:100'],
            'low_stock' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::paginated(
            $useCase(
                $this->scopedBranch($company, $branch, $branches),
                (int) ($validated['per_page'] ?? 15),
                $validated['q'] ?? null,
                (bool) ($validated['low_stock'] ?? false),
            ),
            'Saldo stok.'
        );
    }

    public function movements(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, ItemRepositoryInterface $items, ListStockMovements $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'item_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $itemId = isset($validated['item_id'])
            ? ($items->findForCompany($company, (int) $validated['item_id'])?->id ?? abort(404))
            : null;

        return ApiResponse::paginated(
            $useCase($scopedBranch, (int) ($validated['per_page'] ?? 15), $itemId, $validated['q'] ?? null),
            'Riwayat mutasi stok.'
        );
    }

    public function store(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, RecordStockMovement $useCase): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(['pembelian', 'penjualan', 'penyesuaian'])],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['required_if:type,pembelian', 'nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = $this->scopedBranch($company, $branch, $branches);
        $dto = $useCase(
            $company,
            $branch,
            (int) $validated['item_id'],
            $validated['type'],
            (float) $validated['qty'],
            isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
            $validated['note'] ?? null,
        );

        Audit::record(
            $company, $branch, 'stock.adjust', 'item', (int) $validated['item_id'],
            null,
            ['type' => $validated['type'], 'qty' => (string) $validated['qty']],
        );

        return ApiResponse::success(
            $dto,
            'Mutasi stok dicatat.',
            201
        );
    }

    public function setMinimum(
        Request $request,
        Company $company,
        int $branch,
        int $item,
        BranchRepositoryInterface $branches,
        SetStockMinimum $useCase,
    ): JsonResponse {
        $validated = $request->validate([
            'min_stock' => ['present', 'nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $this->scopedBranch($company, $branch, $branches),
                $item,
                $validated['min_stock'] === null ? null : (float) $validated['min_stock'],
            ),
            'Stok minimum disimpan.'
        );
    }

    private function scopedBranch(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }
}
