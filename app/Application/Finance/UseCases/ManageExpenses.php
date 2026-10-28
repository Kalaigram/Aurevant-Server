<?php

declare(strict_types=1);

namespace App\Application\Finance\UseCases;

use App\Application\Audit\Audit;
use App\Application\Finance\DTOs\ExpenseDTO;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Domain\Finance\Repositories\ExpenseRepositoryInterface;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
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

    public function create(Company $company, array $attributes, ?User $actor = null, ?string $proofPath = null): ExpenseDTO
    {
        $this->assertCategory($company, (int) $attributes['expense_category_id']);
        $this->assertBranch($company, (int) $attributes['branch_id']);

        $created = $this->expenses->createExpense($company, [
            ...$attributes,
            'status' => 'draft',
            'user_id' => $actor?->id,
            'proof_path' => $proofPath,
        ]);
        Audit::record($company, $created->branch_id, 'biaya.catat', 'expense', $created->id);

        Notifier::toPermissioned(
            $company,
            ['biaya.setujui', 'keuangan.lihat'],
            'biaya.diajukan',
            'Biaya '.number_format((float) $created->amount, 0, ',', '.').' menunggu persetujuan.',
            $created->description,
            '/expenses',
            $actor !== null ? [$actor->id] : null,
        );

        return ExpenseDTO::fromModel($created);
    }

    public function approve(Company $company, Expense $expense, ?User $decider = null): ExpenseDTO
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

            Audit::record($company, $saved->branch_id, 'biaya.setujui', 'expense', $saved->id);

            if ($saved->user !== null) {
                Notifier::toUser(
                    $company,
                    $saved->user,
                    'biaya.disetujui',
                    'Biaya '.$saved->description.' disetujui.',
                    null,
                    '/expenses',
                );
            }

            return ExpenseDTO::fromModel($saved);
        });
    }

    public function reject(Company $company, Expense $expense, ?User $decider = null): ExpenseDTO
    {
        if ($expense->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Biaya harus berstatus draft.',
            ]);
        }

        $expense->status = 'ditolak';

        $saved = $this->expenses->saveExpense($expense);
        Audit::record($company, $saved->branch_id, 'biaya.tolak', 'expense', $saved->id);

        if ($saved->user !== null) {
            Notifier::toUser(
                $company,
                $saved->user,
                'biaya.ditolak',
                'Biaya '.$saved->description.' ditolak.',
                null,
                '/expenses',
            );
        }

        return ExpenseDTO::fromModel($saved);
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
