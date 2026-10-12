<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Finance\Repositories\ExpenseRepositoryInterface;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Pagination\LengthAwarePaginator;

final class ExpenseEloquentRepository implements ExpenseRepositoryInterface
{
    public function paginateCategories(Company $company, int $perPage): LengthAwarePaginator
    {
        return ExpenseCategory::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createCategory(Company $company, array $attributes): ExpenseCategory
    {
        return ExpenseCategory::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function findCategory(Company $company, int $id): ?ExpenseCategory
    {
        return ExpenseCategory::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function deleteCategory(ExpenseCategory $category): void
    {
        $category->delete();
    }

    public function paginateExpenses(Company $company, int $perPage, ?int $branchId = null, ?string $status = null): LengthAwarePaginator
    {
        return Expense::query()
            ->with('category')
            ->where('company_id', $company->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findExpense(Company $company, int $id): ?Expense
    {
        return Expense::query()
            ->with('category')
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createExpense(Company $company, array $attributes): Expense
    {
        return Expense::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh()->load('category');
    }

    public function saveExpense(Expense $expense): Expense
    {
        $expense->save();

        return $expense->refresh()->load('category');
    }
}
