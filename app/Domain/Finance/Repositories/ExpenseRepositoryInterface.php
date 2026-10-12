<?php

declare(strict_types=1);

namespace App\Domain\Finance\Repositories;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExpenseRepositoryInterface
{
    /** @return LengthAwarePaginator<int, ExpenseCategory> */
    public function paginateCategories(Company $company, int $perPage): LengthAwarePaginator;

    public function createCategory(Company $company, array $attributes): ExpenseCategory;

    public function findCategory(Company $company, int $id): ?ExpenseCategory;

    public function deleteCategory(ExpenseCategory $category): void;

    /** @return LengthAwarePaginator<int, Expense> */
    public function paginateExpenses(Company $company, int $perPage, ?int $branchId = null, ?string $status = null): LengthAwarePaginator;

    public function findExpense(Company $company, int $id): ?Expense;

    public function createExpense(Company $company, array $attributes): Expense;

    public function saveExpense(Expense $expense): Expense;
}
