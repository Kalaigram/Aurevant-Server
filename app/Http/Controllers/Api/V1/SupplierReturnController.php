<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Purchase\UseCases\CreateSupplierReturn;
use App\Application\Purchase\UseCases\ListSupplierReturns;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Shared\Responses\ApiResponse;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierReturnController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        int $order,
        PurchaseOrderRepositoryInterface $orders,
        ListSupplierReturns $useCase,
    ): JsonResponse {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::paginated(
            $useCase($found, (int) ($validated['per_page'] ?? 15)),
            'Daftar retur supplier.'
        );
    }

    public function store(
        Request $request,
        Company $company,
        int $order,
        int $receipt,
        PurchaseOrderRepositoryInterface $orders,
        GoodsReceiptRepositoryInterface $receipts,
        CreateSupplierReturn $useCase,
    ): JsonResponse {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_receipt_item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        $goodsReceipt = $receipts->findForOrder($found, $receipt) ?? abort(404);

        return ApiResponse::success(
            $useCase(
                $found,
                $goodsReceipt,
                $validated['items'],
                $validated['reason'],
                $validated['note'] ?? null,
            ),
            'Retur supplier dicatat, stok dan hutang berkurang.',
            201
        );
    }

    private function scoped(Company $company, int $id, PurchaseOrderRepositoryInterface $orders): PurchaseOrder
    {
        return $orders->findForCompany($company, $id) ?? abort(404);
    }
}
