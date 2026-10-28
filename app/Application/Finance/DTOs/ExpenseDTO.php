<?php

declare(strict_types=1);

namespace App\Application\Finance\DTOs;

use App\Models\Expense;

final readonly class ExpenseDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public int $categoryId,
        public ?string $categoryName,
        public string $amount,
        public string $method,
        public string $date,
        public string $description,
        public string $status,
        public ?int $userId = null,
        public ?string $proofUrl = null,
        public ?int $recurringExpenseId = null,
    ) {}

    public static function fromModel(Expense $expense): self
    {
        return new self(
            id: $expense->id,
            branchId: $expense->branch_id,
            categoryId: $expense->expense_category_id,
            categoryName: $expense->relationLoaded('category') ? $expense->category?->name : null,
            amount: (string) $expense->amount,
            method: $expense->method,
            date: $expense->date->toDateString(),
            description: $expense->description,
            status: $expense->status,
            userId: $expense->user_id,
            proofUrl: $expense->proof_path !== null ? '/storage/'.$expense->proof_path : null,
            recurringExpenseId: $expense->recurring_expense_id,
        );
    }
}
