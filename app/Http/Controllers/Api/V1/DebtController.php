<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\DTOs\BillDTO;
use App\Application\Finance\DTOs\ReceivableDTO;
use App\Application\Finance\UseCases\ManageReceivables;
use App\Application\Finance\UseCases\PayDebts;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Finance\Repositories\DebtRepositoryInterface;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
    public function bills(Request $request, Company $company, DebtRepositoryInterface $debts): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'unpaid' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::paginated(
            $debts->paginateBills($company, (int) ($validated['per_page'] ?? 15), (bool) ($validated['unpaid'] ?? false))
                ->through(fn ($bill) => BillDTO::fromModel($bill)),
            'Tagihan supplier.'
        );
    }

    public function showBill(Company $company, int $bill, DebtRepositoryInterface $debts): JsonResponse
    {
        $found = $debts->findBill($company, $bill) ?? abort(404);

        return ApiResponse::success(BillDTO::fromModel($found), 'Detail tagihan.');
    }

    public function payBill(Request $request, Company $company, int $bill, DebtRepositoryInterface $debts, PayDebts $useCase): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', Rule::in(['tunai', 'transfer'])],
            'paid_at' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $debts->findBill($company, $bill) ?? abort(404);

        return ApiResponse::success(
            $useCase->payBill(
                $company,
                $found,
                (float) $validated['amount'],
                $validated['method'] ?? 'tunai',
                $validated['paid_at'] ?? null,
                $validated['note'] ?? null,
            ),
            'Hutang dibayar.'
        );
    }

    public function billAging(Company $company, ManageReceivables $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->supplierAging($company), 'Aging hutang.');
    }

    public function receivables(Request $request, Company $company, DebtRepositoryInterface $debts): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'unpaid' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::paginated(
            $debts->paginateReceivables($company, (int) ($validated['per_page'] ?? 15), (bool) ($validated['unpaid'] ?? false))
                ->through(fn ($receivable) => ReceivableDTO::fromModel($receivable)),
            'Daftar piutang.'
        );
    }

    public function storeReceivable(Request $request, Company $company, BranchRepositoryInterface $branches, CustomerRepositoryInterface $customers, ManageReceivables $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = $branches->findForCompany($company, (int) $validated['branch_id']) ?? abort(404);

        if (isset($validated['customer_id'])
            && $customers->findForCompany($company, (int) $validated['customer_id']) === null
        ) {
            abort(404);
        }

        return ApiResponse::success(
            $useCase->record($company, $branch, $validated),
            'Piutang dicatat.',
            201
        );
    }

    public function payReceivable(Request $request, Company $company, int $receivable, DebtRepositoryInterface $debts, ManageReceivables $useCase): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', Rule::in(['tunai', 'transfer', 'qris', 'ewallet'])],
            'paid_at' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $debts->findReceivable($company, $receivable) ?? abort(404);

        return ApiResponse::success(
            $useCase->collect(
                $company,
                $found,
                (float) $validated['amount'],
                $validated['method'] ?? 'tunai',
                $validated['paid_at'] ?? null,
                $validated['note'] ?? null,
            ),
            'Piutang dibayar.'
        );
    }

    public function receivableAging(Company $company, ManageReceivables $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->aging($company), 'Aging piutang.');
    }
}
