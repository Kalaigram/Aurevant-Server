<?php

declare(strict_types=1);

namespace App\Application\Finance\UseCases;

use App\Application\Finance\DTOs\ExpenseDTO;
use App\Application\Ledger\LedgerPoster;
use App\Domain\Finance\Repositories\ExpenseRepositoryInterface;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageExpenses
{
    public function __construct(
        private ExpenseRepositoryInterface $expenses,
        private LedgerPoster $ledgerPoster,
    ) {}

    /** @return LengthAwarePaginator<int, ExpenseDTO> */
    public function list(Company $company, int $perPage, ?int $branchId, ?string $status): LengthAwarePaginator
    {
        return $this->expenses->paginateExpenses($company, $perPage, $branchId, $status)
            ->through(fn ($expense) => ExpenseDTO::fromModel($expense));
    }

    public function create(Company $company, array $attributes): ExpenseDTO
    {
        $this->assertCategory($company, (int) $attributes['expense_category_id']);
        $this->assertBranch($company, (int) $attributes['branch_id']);

        return ExpenseDTO::fromModel(
            $this->expenses->createExpense($company, [...$attributes, 'status' => 'draft'])
        );
    }

    public function approve(Company $company, Expense $expense): ExpenseDTO
    {
        if ($expense->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Biaya harus berstatus draft.',
            ]);
        }

        return DB::transaction(function () use ($company, $expense) {
            $expense->status = 'disetujui';
            $saved = $this->expenses->saveExpense($expense);

            ($this->ledgerPoster)->forExpense($company, $saved);

            return ExpenseDTO::fromModel($saved);
        });
    }

    public function reject(Company $company, Expense $expense): ExpenseDTO
    {
        if ($expense->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Biaya harus berstatus draft.',
            ]);
        }

        $expense->status = 'ditolak';

        return ExpenseDTO::fromModel($this->expenses->saveExpense($expense));
    }

    private function assertCategory(Company $company, int $categoryId): void
    {
        if ($this->expenses->findCategory($company, $categoryId) === null) {
            throw ValidationException::withMessages([
                'expense_category_id' => 'Kategori tidak ada.',
            ]);
        }
    }

    private function assertBranch(Company $company, int $branchId): void
    {
        if ($company->branches()->whereKey($branchId)->doesntExist()) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tidak ada.',
            ]);
        }
    }
}
