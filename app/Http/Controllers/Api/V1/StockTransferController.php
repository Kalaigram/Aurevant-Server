<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Stock\DTOs\StockTransferDTO;
use App\Application\Stock\UseCases\CreateTransfer;
use App\Application\Stock\UseCases\ReceiveTransfer;
use App\Application\Stock\UseCases\ShipTransfer;
use App\Application\Stock\UseCases\TransitionTransfer;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\StockTransfer;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index(Request $request, Company $company, StockTransferRepositoryInterface $transfers): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'diajukan', 'disetujui', 'dikirim', 'diterima', 'dibatalkan'])],
        ]);

        $paginator = $transfers->paginateForCompany($company, (int) ($validated['per_page'] ?? 15), $validated['status'] ?? null);

        return ApiResponse::paginated(
            $paginator->through(fn ($transfer) => StockTransferDTO::fromModel($transfer)),
            'Daftar transfer stok.'
        );
    }

    public function store(Request $request, Company $company, BranchRepositoryInterface $branches, CreateTransfer $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from_branch_id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'integer', 'different:from_branch_id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $from = $branches->findForCompany($company, (int) $validated['from_branch_id']) ?? abort(404);
        $to = $branches->findForCompany($company, (int) $validated['to_branch_id']) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $from, $to, $validated['items'], $validated['note'] ?? null),
            'Transfer dibuat (draft).',
            201
        );
    }

    public function show(Company $company, int $transfer, StockTransferRepositoryInterface $transfers): JsonResponse
    {
        return ApiResponse::success(
            StockTransferDTO::fromModel($this->scoped($company, $transfer, $transfers)),
            'Detail transfer.'
        );
    }

    public function submit(Company $company, int $transfer, StockTransferRepositoryInterface $transfers, TransitionTransfer $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->submit($this->scoped($company, $transfer, $transfers)),
            'Transfer diajukan.'
        );
    }

    public function approve(Company $company, int $transfer, StockTransferRepositoryInterface $transfers, TransitionTransfer $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->approve($this->scoped($company, $transfer, $transfers)),
            'Transfer disetujui.'
        );
    }

    public function ship(Company $company, int $transfer, StockTransferRepositoryInterface $transfers, ShipTransfer $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase($this->scoped($company, $transfer, $transfers)),
            'Transfer dikirim, stok asal berkurang.'
        );
    }

    public function receive(Request $request, Company $company, int $transfer, StockTransferRepositoryInterface $transfers, ReceiveTransfer $useCase): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_received' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(
            $useCase($this->scoped($company, $transfer, $transfers), $validated['items']),
            'Transfer diterima, stok tujuan bertambah.'
        );
    }

    public function cancel(Company $company, int $transfer, StockTransferRepositoryInterface $transfers, TransitionTransfer $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->cancel($this->scoped($company, $transfer, $transfers)),
            'Transfer dibatalkan.'
        );
    }

    private function scoped(Company $company, int $id, StockTransferRepositoryInterface $transfers): StockTransfer
    {
        return $transfers->findForCompany($company, $id) ?? abort(404);
    }
}
