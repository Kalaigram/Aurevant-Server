<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Report\UseCases\ConsolidationReport;
use App\Application\Report\UseCases\DailyReport;
use App\Application\Report\UseCases\RebuildDailySummary;
use App\Application\Report\UseCases\TopProducts;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, Company $company, DailyReport $useCase, BranchRepositoryInterface $branches): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = isset($validated['branch_id'])
            ? ($branches->findForCompany($company, (int) $validated['branch_id'])?->id ?? abort(404))
            : null;

        return ApiResponse::success(
            $useCase($company, $validated['from'], $validated['to'], $branchId),
            'Laporan harian.'
        );
    }

    public function topProducts(
        Request $request,
        Company $company,
        TopProducts $useCase,
        BranchRepositoryInterface $branches,
    ): JsonResponse {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $branchId = isset($validated['branch_id'])
            ? ($branches->findForCompany($company, (int) $validated['branch_id'])?->id ?? abort(404))
            : null;

        return ApiResponse::success(
            $useCase(
                $company,
                $validated['from'],
                $validated['to'],
                $branchId,
                (int) ($validated['limit'] ?? 10),
            ),
            'Top produk.'
        );
    }

    public function consolidation(Request $request, Company $company, ConsolidationReport $useCase): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::success(
            $useCase($company, $validated['from'], $validated['to']),
            'Laporan konsolidasi.'
        );
    }

    public function refresh(Request $request, Company $company, BranchRepositoryInterface $branches, RebuildDailySummary $rebuild): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();

        if (isset($validated['branch_id'])) {
            $branch = $branches->findForCompany($company, (int) $validated['branch_id']) ?? abort(404);
            $rebuild($branch, $date);

            return ApiResponse::success(null, 'Ringkasan dibangun ulang.');
        }

        foreach ($company->branches as $branch) {
            $rebuild($branch, $date);
        }

        return ApiResponse::success(null, 'Ringkasan dibangun ulang.');
    }
}
