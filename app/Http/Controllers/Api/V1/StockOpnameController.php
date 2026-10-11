<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Application\Stock\UseCases\ApproveOpname;
use App\Application\Stock\UseCases\CreateOpname;
use App\Application\Stock\UseCases\ListStockOpnames;
use App\Application\Stock\UseCases\TransitionOpname;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockOpname;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function index(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, ListStockOpnames $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($this->scopedBranch($company, $branch, $branches), (int) ($validated['per_page'] ?? 15)),
            'Daftar opname.'
        );
    }

    public function store(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, CreateOpname $useCase): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty_counted' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $this->scopedBranch($company, $branch, $branches),
                $validated['items'],
                $validated['note'] ?? null
            ),
            'Opname dibuat (draft).',
            201
        );
    }

    public function show(Company $company, int $branch, int $opname, BranchRepositoryInterface $branches, StockOpnameRepositoryInterface $opnames): JsonResponse
    {
        return ApiResponse::success(
            StockOpnameDTO::fromModel($this->scoped($company, $branch, $opname, $branches, $opnames)),
            'Detail opname.'
        );
    }

    public function submit(Company $company, int $branch, int $opname, BranchRepositoryInterface $branches, StockOpnameRepositoryInterface $opnames, TransitionOpname $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->submit($this->scoped($company, $branch, $opname, $branches, $opnames)),
            'Opname diajukan.'
        );
    }

    public function approve(Company $company, int $branch, int $opname, BranchRepositoryInterface $branches, StockOpnameRepositoryInterface $opnames, ApproveOpname $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase($this->scoped($company, $branch, $opname, $branches, $opnames)),
            'Opname disetujui, selisih diterapkan ke stok.'
        );
    }

    public function reject(Company $company, int $branch, int $opname, BranchRepositoryInterface $branches, StockOpnameRepositoryInterface $opnames, TransitionOpname $flow): JsonResponse
    {
        return ApiResponse::success(
            $flow->reject($this->scoped($company, $branch, $opname, $branches, $opnames)),
            'Opname ditolak.'
        );
    }

    private function scopedBranch(Company $company, int $id, BranchRepositoryInterface $branches): Branch
    {
        return $branches->findForCompany($company, $id) ?? abort(404);
    }

    private function scoped(Company $company, int $branchId, int $id, BranchRepositoryInterface $branches, StockOpnameRepositoryInterface $opnames): StockOpname
    {
        $branch = $this->scopedBranch($company, $branchId, $branches);

        return $opnames->findForBranch($branch, $id) ?? abort(404);
    }
}
