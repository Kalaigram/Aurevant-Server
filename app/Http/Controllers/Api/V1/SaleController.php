<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Sale\DTOs\SaleDTO;
use App\Application\Sale\DTOs\SaleReturnDTO;
use App\Application\Sale\UseCases\CreateReturn;
use App\Application\Sale\UseCases\CreateSale;
use App\Application\Sale\UseCases\PrintReceipt;
use App\Application\Sale\UseCases\VoidSale;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Domain\Sale\Repositories\SaleReturnRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleController extends Controller
{
    public function index(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, SaleRepositoryInterface $sales): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $sales->paginateForBranch(
            $this->scopedBranch($company, $branch, $branches),
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponse::paginated(
            $paginator->through(fn ($sale) => SaleDTO::fromModel($sale)),
            'Daftar nota.'
        );
    }

    public function store(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, CreateSale $useCase): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', Rule::in(['tunai', 'transfer', 'qris', 'ewallet', 'dp'])],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $useCase(
            $company,
            $this->scopedBranch($company, $branch, $branches),
            $request->user(),
            $validated['items'],
            $validated['payments'],
            $validated['idempotency_key'] ?? null,
        );

        if ($result['duplicate']) {
            return ApiResponse::success($result['sale'], 'Duplikat dicegah (idempotent).');
        }

        return ApiResponse::success($result['sale'], 'Nota terbit.', 201);
    }

    public function show(Company $company, int $branch, int $sale, BranchRepositoryInterface $branches, SaleRepositoryInterface $sales): JsonResponse
    {
        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $found = $sales->findForBranch($scopedBranch, $sale) ?? abort(404);

        return ApiResponse::success(SaleDTO::fromModel($found), 'Detail nota.');
    }

    public function receipt(Company $company, int $branch, int $sale, BranchRepositoryInterface $branches, SaleRepositoryInterface $sales, PrintReceipt $useCase): StreamedResponse
    {
        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $found = $sales->findForBranch($scopedBranch, $sale) ?? abort(404);

        $pdf = Pdf::loadView('receipts.sale', $useCase($company, $scopedBranch, $found));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "struk-{$found->number}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function void(Request $request, Company $company, int $branch, int $sale, BranchRepositoryInterface $branches, SaleRepositoryInterface $sales, VoidSale $useCase): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $found = $sales->findForBranch($scopedBranch, $sale) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $scopedBranch, $found, $validated['reason']),
            'Nota divoid, stok dikembalikan.'
        );
    }

    public function returns(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, SaleReturnRepositoryInterface $returns): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $returns->paginateForBranch(
            $this->scopedBranch($company, $branch, $branches),
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponse::paginated(
            $paginator->through(fn ($retur) => SaleReturnDTO::fromModel($retur)),
            'Daftar void/retur.'
        );
    }

    public function storeReturn(Request $request, Company $company, int $branch, int $sale, BranchRepositoryInterface $branches, SaleRepositoryInterface $sales, CreateReturn $useCase): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['required', 'string', Rule::in(['tunai', 'transfer', 'qris', 'ewallet'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.condition' => ['required', 'string', Rule::in(['layak', 'rusak'])],
        ]);

        $scopedBranch = $this->scopedBranch($company, $branch, $branches);
        $found = $sales->findForBranch($scopedBranch, $sale) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $scopedBranch, $found, $validated['items'], $validated['reason'], $validated['refund_method']),
            'Retur dicatat.',
            201
        );
    }

    private function scopedBranch(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }
}
