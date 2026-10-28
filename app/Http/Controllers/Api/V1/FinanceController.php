<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Finance\DTOs\ExpenseDTO;
use App\Application\Finance\UseCases\ManageExpenses;
use App\Domain\Finance\Repositories\ExpenseRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function categories(Request $request, Company $company, ExpenseRepositoryInterface $expenses): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $expenses->paginateCategories($company, (int) ($validated['per_page'] ?? 15)),
            'Kategori biaya.'
        );
    }

    public function storeCategory(Request $request, Company $company, ExpenseRepositoryInterface $expenses): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('expense_categories', 'name')->where('company_id', $company->id),
            ],
        ]);

        return ApiResponse::success(
            $expenses->createCategory($company, $validated),
            'Kategori dibuat.',
            201
        );
    }

    public function destroyCategory(Company $company, int $category, ExpenseRepositoryInterface $expenses): JsonResponse
    {
        $found = $expenses->findCategory($company, $category) ?? abort(404);
        $expenses->deleteCategory($found);

        return ApiResponse::success(null, 'Kategori dihapus.');
    }

    public function index(Request $request, Company $company, ManageExpenses $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'branch_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'disetujui', 'ditolak'])],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                $company,
                (int) ($validated['per_page'] ?? 15),
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                $validated['status'] ?? null,
            ),
            'Daftar biaya.'
        );
    }

    public function store(Request $request, Company $company, ManageExpenses $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', Rule::in(['tunai', 'transfer'])],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'proof' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:2048'],
        ]);

        $proofPath = $request->file('proof')?->store('bukti-biaya', 'public') ?: null;
        unset($validated['proof']);

        return ApiResponse::success(
            $useCase->create($company, $validated, $request->user(), $proofPath),
            'Biaya dicatat (draft).',
            201
        );
    }

    public function show(Company $company, int $expense, ExpenseRepositoryInterface $expenses): JsonResponse
    {
        $found = $expenses->findExpense($company, $expense) ?? abort(404);

        return ApiResponse::success(ExpenseDTO::fromModel($found), 'Detail biaya.');
    }

    public function approve(Request $request, Company $company, int $expense, ExpenseRepositoryInterface $expenses, ManageExpenses $useCase): JsonResponse
    {
        $found = $expenses->findExpense($company, $expense) ?? abort(404);

        return ApiResponse::success($useCase->approve($company, $found, $request->user()), 'Biaya disetujui.');
    }

    public function reject(Request $request, Company $company, int $expense, ExpenseRepositoryInterface $expenses, ManageExpenses $useCase): JsonResponse
    {
        $found = $expenses->findExpense($company, $expense) ?? abort(404);

        return ApiResponse::success($useCase->reject($company, $found, $request->user()), 'Biaya ditolak.');
    }
}
