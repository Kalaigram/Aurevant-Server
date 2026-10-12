<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ledger\UseCases\BalanceSheet;
use App\Application\Ledger\UseCases\CashFlow;
use App\Application\Ledger\UseCases\ProfitLoss;
use App\Application\Ledger\UseCases\TrialBalance;
use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function entries(Request $request, Company $company, LedgerRepositoryInterface $ledger): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::paginated(
            $ledger->paginateEntries(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            ),
            'Daftar jurnal.'
        );
    }

    public function profitLoss(Request $request, Company $company, ProfitLoss $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $validated['from'],
                $validated['to'],
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            ),
            'Laba rugi.'
        );
    }

    public function trialBalance(Request $request, Company $company, TrialBalance $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::success(
            $useCase($company, $validated['from'], $validated['to']),
            'Neraca saldo.'
        );
    }

    public function cashFlow(Request $request, Company $company, CashFlow $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $validated['from'],
                $validated['to'],
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            ),
            'Arus kas.'
        );
    }

    public function balanceSheet(Request $request, Company $company, BalanceSheet $useCase): JsonResponse
    {
        $validated = $request->validate([
            'as_of' => ['sometimes', 'date'],
        ]);

        return ApiResponse::success(
            $useCase($company, $validated['as_of'] ?? now()->toDateString()),
            'Neraca.'
        );
    }
}
