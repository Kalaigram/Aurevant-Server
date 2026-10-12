<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ledger\DTOs\AccountingPeriodDTO;
use App\Application\Ledger\UseCases\ClosePeriod;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function index(Request $request, Company $company, ClosePeriod $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->list($company, (int) ($validated['per_page'] ?? 15)),
            'Periode akuntansi.'
        );
    }

    public function close(Request $request, Company $company, ClosePeriod $useCase): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return ApiResponse::success(
            $useCase->close($company, (int) $validated['year'], (int) $validated['month']),
            'Periode ditutup.'
        );
    }

    public function reopen(Company $company, int $period, ClosePeriod $useCase): JsonResponse
    {
        $found = AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->whereKey($period)
            ->first() ?? abort(404);

        return ApiResponse::success(
            $useCase->reopen($company, $found),
            'Periode dibuka kembali.'
        );
    }

    public function show(Company $company, int $period): JsonResponse
    {
        $found = AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->whereKey($period)
            ->first() ?? abort(404);

        return ApiResponse::success(AccountingPeriodDTO::fromModel($found), 'Detail periode.');
    }
}
