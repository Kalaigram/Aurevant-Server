<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Purchase\DTOs\PurchaseOrderDTO;
use App\Application\Purchase\UseCases\CreatePurchaseOrder;
use App\Application\Purchase\UseCases\ListGoodsReceipts;
use App\Application\Purchase\UseCases\ListPurchaseOrders;
use App\Application\Purchase\UseCases\ReceivePurchaseOrder;
use App\Application\Purchase\UseCases\TransitionPurchaseOrder;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Shared\Responses\ApiResponse;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, Company $company, ListPurchaseOrders $useCase, BranchRepositoryInterface $branches): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'diajukan', 'disetujui', 'dipesan', 'selesai', 'dibatalkan'])],
            'branch_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);

        $branchId = isset($validated['branch_id'])
            ? ($branches->findForCompany($company, (int) $validated['branch_id'])?->id ?? abort(404))
            : null;

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15), $validated['status'] ?? null, $branchId, $validated['q'] ?? null),
            'Daftar PO.'
        );
    }

    public function store(Request $request, Company $company, BranchRepositoryInterface $branches, SupplierRepositoryInterface $suppliers, CreatePurchaseOrder $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_ordered' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = $branches->findForCompany($company, (int) $validated['branch_id']) ?? abort(404);
        $supplier = $suppliers->findForCompany($company, (int) $validated['supplier_id']) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $branch, $supplier, $validated['items'], $validated['note'] ?? null),
            'PO dibuat (draft).',
            201
        );
    }

    public function show(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders): JsonResponse
    {
        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            PurchaseOrderDTO::fromModel($found),
            'Detail PO.'
        );
    }

    public function submit(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, TransitionPurchaseOrder $flow): JsonResponse
    {
        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            $flow->submit($found),
            'PO diajukan.'
        );
    }

    public function approve(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, TransitionPurchaseOrder $flow): JsonResponse
    {
        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            $flow->approve($found),
            'PO disetujui.'
        );
    }

    public function order(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, TransitionPurchaseOrder $flow): JsonResponse
    {
        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            $flow->order($found),
            'PO dipesan ke supplier.'
        );
    }

    public function receive(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, ReceivePurchaseOrder $useCase): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_received' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            $useCase(
                $found,
                $validated['items'],
                $validated['due_at'] ?? null,
                $validated['note'] ?? null,
            ),
            'Penerimaan dicatat, stok dan tagihan bertambah.'
        );
    }

    public function cancel(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, TransitionPurchaseOrder $flow): JsonResponse
    {
        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::success(
            $flow->cancel($found),
            'PO dibatalkan.'
        );
    }

    public function receipts(Request $request, Company $company, int $order, PurchaseOrderRepositoryInterface $orders, ListGoodsReceipts $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $found = $this->scoped($company, $order, $orders);
        Access::requireBranch($request->user(), $company, $found->branch_id);

        return ApiResponse::paginated(
            $useCase($found, (int) ($validated['per_page'] ?? 15)),
            'Daftar penerimaan.'
        );
    }

    private function scoped(Company $company, int $id, PurchaseOrderRepositoryInterface $orders): PurchaseOrder
    {
        return $orders->findForCompany($company, $id) ?? abort(404);
    }
}
