<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Sale\DTOs\ShiftDTO;
use App\Application\Sale\UseCases\CloseShift;
use App\Application\Sale\UseCases\OpenShift;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function open(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, OpenShift $useCase): JsonResponse
    {
        $validated = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(
            $useCase(
                $this->scopedBranch($company, $branch, $branches),
                $request->user()->id,
                (string) $validated['opening_cash'],
            ),
            'Shift dibuka.',
            201
        );
    }

    public function show(Company $company, int $branch, int $shift, BranchRepositoryInterface $branches, CashShiftRepositoryInterface $shifts): JsonResponse
    {
        return ApiResponse::success(
            ShiftDTO::fromModel($this->scoped($company, $branch, $shift, $branches, $shifts)),
            'Detail shift.'
        );
    }

    public function close(Request $request, Company $company, int $branch, int $shift, BranchRepositoryInterface $branches, CashShiftRepositoryInterface $shifts, CloseShift $useCase): JsonResponse
    {
        $validated = $request->validate([
            'closing_cash_counted' => ['required', 'numeric', 'min:0'],
        ]);

        return ApiResponse::success(
            $useCase(
                $this->scoped($company, $branch, $shift, $branches, $shifts),
                (string) $validated['closing_cash_counted'],
            ),
            'Shift ditutup, selisih tercatat.'
        );
    }

    private function scopedBranch(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }

    private function scoped(Company $company, int $branchId, int $id, BranchRepositoryInterface $branches, CashShiftRepositoryInterface $shifts): CashShift
    {
        $branch = $this->scopedBranch($company, $branchId, $branches);

        return $shifts->findForBranch($branch, $id) ?? abort(404);
    }
}
