<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Application\Service\UseCases\AddDeposit;
use App\Application\Service\UseCases\CompleteServiceOrder;
use App\Application\Service\UseCases\CreateServiceOrder;
use App\Application\Service\UseCases\ListServiceOrders;
use App\Application\Service\UseCases\PayServiceOrder;
use App\Application\Service\UseCases\PrintServiceEstimate;
use App\Application\Service\UseCases\TransitionServiceOrder;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Shared\Responses\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderController extends Controller
{
    public function index(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, ListServiceOrders $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['baru', 'diproses', 'selesai', 'dibayar', 'dibatalkan'])],
            'q' => ['sometimes', 'string', 'max:100'],
            'scheduled_from' => ['sometimes', 'date'],
            'scheduled_to' => ['sometimes', 'date', 'after_or_equal:scheduled_from'],
        ]);

        return ApiResponse::paginated(
            $useCase(
                $this->scopedBranch($company, $branch, $branches),
                (int) ($validated['per_page'] ?? 15),
                $validated['status'] ?? null,
                $validated['q'] ?? null,
                $validated['scheduled_from'] ?? null,
                $validated['scheduled_to'] ?? null,
            ),
            'Daftar order jasa.'
        );
    }

    public function store(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, CreateServiceOrder $useCase): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'min:1', 'required_without:package_id'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $this->scopedBranch($company, $branch, $branches),
                $validated['items'] ?? [],
                $validated['customer_id'] ?? null,
                $validated['scheduled_at'] ?? null,
                $validated['note'] ?? null,
                $validated['package_id'] ?? null,
            ),
            'Order jasa dibuat.',
            201
        );
    }

    public function show(Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders): JsonResponse
    {
        return ApiResponse::success(
            ServiceOrderDTO::fromModel($this->scoped($company, $branch, $order, $branches, $orders)),
            'Detail order jasa.'
        );
    }

    public function estimate(Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, PrintServiceEstimate $useCase): StreamedResponse
    {
        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $found = $orders->findForBranch($scopedBranch, $order) ?? abort(404);

        $pdf = Pdf::loadView('estimates.service-order', $useCase($company, $scopedBranch, $found));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "estimasi-jasa-{$found->id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function deposit(Request $request, Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, AddDeposit $useCase): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return ApiResponse::success(
            $useCase(
                $this->scoped($company, $branch, $order, $branches, $orders),
                (float) $validated['amount']
            ),
            'DP dicatat.'
        );
    }

    public function process(Request $request, Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, TransitionServiceOrder $flow): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return ApiResponse::success(
            $flow->process(
                $this->scoped($company, $branch, $order, $branches, $orders),
                isset($validated['assignee_id']) ? (int) $validated['assignee_id'] : null,
            ),
            'Order diproses.'
        );
    }

    public function complete(Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, CompleteServiceOrder $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase($this->scoped($company, $branch, $order, $branches, $orders)),
            'Jasa selesai, bahan terpotong dan komisi tercatat.'
        );
    }

    public function pay(Request $request, Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, PayServiceOrder $useCase): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', Rule::in(['tunai', 'transfer', 'qris', 'ewallet'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $this->scopedBranch($company, $branch, $branches),
                $request->user(),
                $this->scoped($company, $branch, $order, $branches, $orders),
                $validated['payments'],
                $validated['idempotency_key'] ?? null,
            ),
            'Jasa dibayar, nota terbit.',
            201
        );
    }

    public function cancel(Company $company, int $branch, int $order, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders, TransitionServiceOrder $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->cancel($this->scoped($company, $branch, $order, $branches, $orders)),
            'Order dibatalkan.'
        );
    }

    private function scopedBranch(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }

    private function scoped(Company $company, int $branchId, int $id, BranchRepositoryInterface $branches, ServiceOrderRepositoryInterface $orders): ServiceOrder
    {
        $branch = $this->scopedBranch($company, $branchId, $branches);

        return $orders->findForBranch($branch, $id) ?? abort(404);
    }
}
