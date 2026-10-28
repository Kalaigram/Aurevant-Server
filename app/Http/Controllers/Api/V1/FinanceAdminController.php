<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\Audit;
use App\Application\Notify\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\Budget;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use App\Models\JournalLine;
use App\Models\RecurringExpense;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceAdminController extends Controller
{
    // ---------------------------------------------------------------- COA

    public function accounts(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(['aset', 'liabilitas', 'ekuitas', 'pendapatan', 'beban'])],
            'active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(array_key_exists('active', $validated), fn ($query) => $query->where('is_active', (bool) $validated['active']))
            ->when($validated['q'] ?? null, fn ($query, $q) => $query->where(function ($scope) use ($q): void {
                $scope->where('code', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%');
            }))
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => $this->accountRow($account));

        return ApiResponse::success($accounts, 'Daftar akun.');
    }

    public function storeAccount(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate($this->accountRules($company));

        $account = Account::query()->create([
            'company_id' => $company->id,
            ...$validated,
        ]);

        Audit::record($company, null, 'akun.buat', 'account', $account->id, null, ['code' => $account->code]);

        return ApiResponse::success($this->accountRow($account), 'Akun dibuat.', 201);
    }

    public function updateAccount(Request $request, Company $company, int $account): JsonResponse
    {
        $found = Account::query()->where('company_id', $company->id)->whereKey($account)->first() ?? abort(404);
        $validated = $request->validate($this->accountRules($company, $found->id));

        if (isset($validated['code']) && $validated['code'] !== $found->code && $found->lines()->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Kode akun yang sudah memiliki jurnal tidak boleh diubah.',
            ]);
        }

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $this->assertAccountParent($company, (int) $validated['parent_id'], $found->id);
        }

        $found->update($validated);
        Audit::record($company, null, 'akun.ubah', 'account', $found->id);

        return ApiResponse::success($this->accountRow($found->refresh()), 'Akun diperbarui.');
    }

    public function destroyAccount(Company $company, int $account): JsonResponse
    {
        $found = Account::query()->where('company_id', $company->id)->whereKey($account)->first() ?? abort(404);

        if ($found->lines()->exists() || $found->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Akun memiliki jurnal atau sub-akun. Nonaktifkan saja.',
            ]);
        }

        $found->delete();

        return ApiResponse::success(null, 'Akun dihapus.');
    }

    // ------------------------------------------------------------- Budget

    public function budgets(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'branch_id' => ['sometimes', 'integer'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);

        $rows = Budget::query()
            ->with(['branch', 'category'])
            ->where('company_id', $company->id)
            ->where('year', $year)
            ->when(isset($validated['month']), fn ($query) => $query->where('month', (int) $validated['month']))
            ->when(isset($validated['branch_id']), fn ($query) => $query->where('branch_id', (int) $validated['branch_id']))
            ->orderBy('month')
            ->orderBy('id')
            ->get()
            ->map(fn (Budget $budget) => $this->budgetRow($budget));

        return ApiResponse::success($rows, 'Daftar anggaran.');
    }

    public function storeBudget(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate($this->budgetRules($company));
        $this->assertOptionalCategory($company, $validated['expense_category_id'] ?? null);

        $budget = Budget::query()->create([
            'company_id' => $company->id,
            ...$validated,
        ])->load(['branch', 'category']);

        Audit::record($company, $validated['branch_id'] ?? null, 'anggaran.buat', 'budget', $budget->id);

        return ApiResponse::success($this->budgetRow($budget), 'Anggaran dibuat.', 201);
    }

    public function updateBudget(Request $request, Company $company, int $budget): JsonResponse
    {
        $found = Budget::query()->where('company_id', $company->id)->whereKey($budget)->first() ?? abort(404);
        $validated = $request->validate($this->budgetRules($company));
        $this->assertOptionalCategory($company, $validated['expense_category_id'] ?? null);

        $found->update($validated);
        Audit::record($company, $found->branch_id, 'anggaran.ubah', 'budget', $found->id);

        return ApiResponse::success($this->budgetRow($found->refresh()->load(['branch', 'category'])), 'Anggaran diperbarui.');
    }

    public function destroyBudget(Company $company, int $budget): JsonResponse
    {
        $found = Budget::query()->where('company_id', $company->id)->whereKey($budget)->first() ?? abort(404);
        $found->delete();

        return ApiResponse::success(null, 'Anggaran dihapus.');
    }

    // ---------------------------------------------------------- Recurring

    public function recurring(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
        ]);

        $rows = RecurringExpense::query()
            ->with(['branch', 'category'])
            ->where('company_id', $company->id)
            ->when(array_key_exists('active', $validated), fn ($query) => $query->where('is_active', (bool) $validated['active']))
            ->orderByDesc('is_active')
            ->orderBy('next_run_at')
            ->get()
            ->map(fn (RecurringExpense $row) => $this->recurringRow($row));

        return ApiResponse::success($rows, 'Biaya berulang.');
    }

    public function storeRecurring(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate($this->recurringRules($company));
        $this->assertOptionalCategory($company, $validated['expense_category_id'] ?? null);

        $row = RecurringExpense::query()->create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            ...$validated,
        ])->load(['branch', 'category']);

        Audit::record($company, $row->branch_id, 'biaya_berulang.buat', 'recurring_expense', $row->id);

        return ApiResponse::success($this->recurringRow($row), 'Template biaya berulang dibuat.', 201);
    }

    public function updateRecurring(Request $request, Company $company, int $recurring): JsonResponse
    {
        $found = RecurringExpense::query()->where('company_id', $company->id)->whereKey($recurring)->first() ?? abort(404);
        $validated = $request->validate($this->recurringRules($company));
        $this->assertOptionalCategory($company, $validated['expense_category_id'] ?? null);

        $found->update($validated);
        Audit::record($company, $found->branch_id, 'biaya_berulang.ubah', 'recurring_expense', $found->id);

        return ApiResponse::success(
            $this->recurringRow($found->refresh()->load(['branch', 'category'])),
            'Template biaya berulang diperbarui.'
        );
    }

    public function destroyRecurring(Company $company, int $recurring): JsonResponse
    {
        $found = RecurringExpense::query()->where('company_id', $company->id)->whereKey($recurring)->first() ?? abort(404);
        $found->delete();

        return ApiResponse::success(null, 'Template biaya berulang dihapus.');
    }

    public function runRecurring(Company $company): JsonResponse
    {
        $created = 0;
        $today = now()->startOfDay();

        DB::transaction(function () use ($company, $today, &$created): void {
            $rows = RecurringExpense::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->whereDate('next_run_at', '<=', $today->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $runs = 0;
                while ($row->next_run_at->lte($today) && $runs < 24) {
                    Expense::query()->create([
                        'company_id' => $company->id,
                        'branch_id' => $row->branch_id,
                        'expense_category_id' => $row->expense_category_id,
                        'user_id' => $row->user_id,
                        'recurring_expense_id' => $row->id,
                        'amount' => $row->amount,
                        'method' => $row->method,
                        'date' => $row->next_run_at->toDateString(),
                        'description' => $row->description,
                        'status' => 'draft',
                    ]);

                    $row->last_run_at = $row->next_run_at;
                    $row->next_run_at = $this->nextRun($row->next_run_at, $row->frequency);
                    $created++;
                    $runs++;
                }

                $row->save();
            }
        });

        return ApiResponse::success(['created' => $created], 'Biaya berulang diproses.');
    }

    // ------------------------------------------------------- Fixed assets

    public function fixedAssets(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['aktif', 'dilepas'])],
        ]);

        $rows = FixedAsset::query()
            ->with('branch')
            ->where('company_id', $company->id)
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderBy('acquisition_date')
            ->get()
            ->map(fn (FixedAsset $asset) => $this->assetRow($asset));

        return ApiResponse::success($rows, 'Daftar aset tetap.');
    }

    public function storeFixedAsset(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate($this->assetRules($company));
        $this->assertSalvage($validated);

        $asset = FixedAsset::query()->create([
            'company_id' => $company->id,
            ...$validated,
        ])->load('branch');

        Audit::record($company, $validated['branch_id'] ?? null, 'aset.buat', 'fixed_asset', $asset->id);

        return ApiResponse::success($this->assetRow($asset), 'Aset tetap dibuat.', 201);
    }

    public function updateFixedAsset(Request $request, Company $company, int $asset): JsonResponse
    {
        $found = FixedAsset::query()->where('company_id', $company->id)->whereKey($asset)->first() ?? abort(404);
        $validated = $request->validate($this->assetRules($company));
        $this->assertSalvage($validated);

        $found->update($validated);
        Audit::record($company, $found->branch_id, 'aset.ubah', 'fixed_asset', $found->id);

        return ApiResponse::success($this->assetRow($found->refresh()->load('branch')), 'Aset tetap diperbarui.');
    }

    public function destroyFixedAsset(Company $company, int $asset): JsonResponse
    {
        $found = FixedAsset::query()->where('company_id', $company->id)->whereKey($asset)->first() ?? abort(404);
        $found->delete();

        return ApiResponse::success(null, 'Aset tetap dihapus.');
    }

    public function depreciate(Request $request, Company $company, int $asset): JsonResponse
    {
        $validated = $request->validate([
            'months' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ]);

        $found = FixedAsset::query()->where('company_id', $company->id)->whereKey($asset)->first() ?? abort(404);

        if ($found->status !== 'aktif') {
            throw ValidationException::withMessages(['status' => 'Aset sudah tidak aktif.']);
        }

        $monthly = $found->monthlyDepreciation();
        $depreciable = (float) $found->acquisition_cost - (float) $found->salvage_value;
        $remaining = round($depreciable - (float) $found->accumulated_depreciation, 2);

        if ($monthly <= 0 || $remaining <= 0) {
            throw ValidationException::withMessages(['asset' => 'Aset sudah habis disusutkan.']);
        }

        $months = (int) ($validated['months'] ?? 1);
        $amount = min($remaining, round($monthly * $months, 2));

        $found->accumulated_depreciation = round((float) $found->accumulated_depreciation + $amount, 2);
        $found->last_depreciated_at = now()->toDateString();
        $found->save();

        Audit::record($company, $found->branch_id, 'aset.susut', 'fixed_asset', $found->id, null, ['amount' => $amount]);

        return ApiResponse::success($this->assetRow($found->refresh()->load('branch')), 'Penyusutan dicatat.');
    }

    // ---------------------------------------------------- Reconciliation

    public function reconciliations(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', 'date_format:Y-m'],
            'account_id' => ['sometimes', 'integer'],
        ]);

        $rows = BankReconciliation::query()
            ->with(['account', 'branch'])
            ->where('company_id', $company->id)
            ->when(isset($validated['period']), fn ($query) => $query->where('period', $validated['period']))
            ->when(isset($validated['account_id']), fn ($query) => $query->where('account_id', (int) $validated['account_id']))
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BankReconciliation $row) => $this->reconciliationRow($row));

        return ApiResponse::success($rows, 'Rekonsiliasi bank.');
    }

    public function storeReconciliation(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate($this->reconciliationRules($company));

        if (BankReconciliation::query()
            ->where('company_id', $company->id)
            ->where('account_id', (int) $validated['account_id'])
            ->where('period', $validated['period'])
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'period' => 'Rekonsiliasi akun dan periode ini sudah ada.',
            ]);
        }

        $book = $this->bookBalance(
            $company,
            (int) $validated['account_id'],
            $validated['period'],
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );

        $row = BankReconciliation::query()->create([
            'company_id' => $company->id,
            'account_id' => (int) $validated['account_id'],
            'branch_id' => $validated['branch_id'] ?? null,
            'period' => $validated['period'],
            'statement_balance' => $validated['statement_balance'],
            'book_balance' => $book,
            'difference' => round((float) $validated['statement_balance'] - $book, 2),
            'status' => 'draft',
            'note' => $validated['note'] ?? null,
        ])->load(['account', 'branch']);

        Audit::record($company, $row->branch_id, 'rekonsiliasi.buat', 'bank_reconciliation', $row->id);

        return ApiResponse::success($this->reconciliationRow($row), 'Rekonsiliasi dibuat.', 201);
    }

    public function updateReconciliation(Request $request, Company $company, int $reconciliation): JsonResponse
    {
        $found = BankReconciliation::query()
            ->with(['account', 'branch'])
            ->where('company_id', $company->id)
            ->whereKey($reconciliation)
            ->first() ?? abort(404);

        if ($found->status === 'selesai') {
            throw ValidationException::withMessages(['status' => 'Rekonsiliasi sudah diselesaikan.']);
        }

        $validated = $request->validate([
            'statement_balance' => ['sometimes', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('statement_balance', $validated)) {
            $found->statement_balance = $validated['statement_balance'];
            $found->book_balance = $this->bookBalance($company, $found->account_id, $found->period, $found->branch_id);
            $found->difference = round((float) $found->statement_balance - (float) $found->book_balance, 2);
        }

        if (array_key_exists('note', $validated)) {
            $found->note = $validated['note'];
        }

        $found->save();

        return ApiResponse::success($this->reconciliationRow($found->refresh()->load(['account', 'branch'])), 'Rekonsiliasi diperbarui.');
    }

    public function resolveReconciliation(Request $request, Company $company, int $reconciliation): JsonResponse
    {
        $found = BankReconciliation::query()
            ->with(['account', 'branch'])
            ->where('company_id', $company->id)
            ->whereKey($reconciliation)
            ->first() ?? abort(404);

        if ($found->status === 'selesai') {
            throw ValidationException::withMessages(['status' => 'Rekonsiliasi sudah diselesaikan.']);
        }

        $found->status = 'selesai';
        $found->reconciled_by = $request->user()->id;
        $found->reconciled_at = now();
        $found->save();

        Audit::record($company, $found->branch_id, 'rekonsiliasi.selesai', 'bank_reconciliation', $found->id);
        Notifier::toPermissioned(
            $company,
            ['keuangan.lihat', 'periode.tutup'],
            'rekonsiliasi.selesai',
            'Rekonsiliasi '.$found->account?->name.' periode '.$found->period.' diselesaikan.',
            'Selisih '.number_format((float) $found->difference, 2, ',', '.'),
            '/finance',
            $request->user()->id,
        );

        return ApiResponse::success($this->reconciliationRow($found->refresh()->load(['account', 'branch'])), 'Rekonsiliasi diselesaikan.');
    }

    // ------------------------------------------------------------- helpers

    /** @return list<array<string, mixed>> */
    private function accountRow(Account $account): array
    {
        return [
            'id' => $account->id,
            'parentId' => $account->parent_id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'isActive' => (bool) $account->is_active,
            'description' => $account->description,
        ];
    }

    /** @return array<string, mixed> */
    private function budgetRow(Budget $budget): array
    {
        $used = $this->budgetUsed($budget);

        return [
            'id' => $budget->id,
            'year' => $budget->year,
            'month' => $budget->month,
            'branchId' => $budget->branch_id,
            'branchName' => $budget->relationLoaded('branch') ? $budget->branch?->name : null,
            'categoryId' => $budget->expense_category_id,
            'categoryName' => $budget->relationLoaded('category') ? $budget->category?->name : null,
            'amount' => (string) $budget->amount,
            'used' => number_format($used, 2, '.', ''),
            'remaining' => number_format((float) $budget->amount - $used, 2, '.', ''),
            'note' => $budget->note,
        ];
    }

    private function budgetUsed(Budget $budget): float
    {
        $start = Carbon::create($budget->year, $budget->month ?? 1, 1)->startOfMonth();
        $end = $budget->month === null
            ? Carbon::create($budget->year, 12, 31)->endOfDay()
            : $start->copy()->endOfMonth();

        return round((float) Expense::query()
            ->where('company_id', $budget->company_id)
            ->where('status', 'disetujui')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($budget->branch_id !== null, fn ($query) => $query->where('branch_id', $budget->branch_id))
            ->when($budget->expense_category_id !== null, fn ($query) => $query->where('expense_category_id', $budget->expense_category_id))
            ->sum('amount'), 2);
    }

    /** @return array<string, mixed> */
    private function recurringRow(RecurringExpense $row): array
    {
        return [
            'id' => $row->id,
            'branchId' => $row->branch_id,
            'branchName' => $row->relationLoaded('branch') ? $row->branch?->name : null,
            'categoryId' => $row->expense_category_id,
            'categoryName' => $row->relationLoaded('category') ? $row->category?->name : null,
            'amount' => (string) $row->amount,
            'method' => $row->method,
            'description' => $row->description,
            'frequency' => $row->frequency,
            'nextRunAt' => $row->next_run_at->toDateString(),
            'lastRunAt' => $row->last_run_at?->toDateString(),
            'isActive' => (bool) $row->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function assetRow(FixedAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'branchId' => $asset->branch_id,
            'branchName' => $asset->relationLoaded('branch') ? $asset->branch?->name : null,
            'name' => $asset->name,
            'code' => $asset->code,
            'category' => $asset->category,
            'acquisitionDate' => $asset->acquisition_date->toDateString(),
            'acquisitionCost' => (string) $asset->acquisition_cost,
            'salvageValue' => (string) $asset->salvage_value,
            'usefulLifeMonths' => $asset->useful_life_months,
            'accumulatedDepreciation' => (string) $asset->accumulated_depreciation,
            'monthlyDepreciation' => number_format($asset->monthlyDepreciation(), 2, '.', ''),
            'bookValue' => number_format($asset->bookValue(), 2, '.', ''),
            'status' => $asset->status,
            'note' => $asset->note,
            'lastDepreciatedAt' => $asset->last_depreciated_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function reconciliationRow(BankReconciliation $row): array
    {
        return [
            'id' => $row->id,
            'branchId' => $row->branch_id,
            'branchName' => $row->relationLoaded('branch') ? $row->branch?->name : null,
            'accountId' => $row->account_id,
            'accountCode' => $row->relationLoaded('account') ? $row->account?->code : null,
            'accountName' => $row->relationLoaded('account') ? $row->account?->name : null,
            'period' => $row->period,
            'statementBalance' => (string) $row->statement_balance,
            'bookBalance' => (string) $row->book_balance,
            'difference' => (string) $row->difference,
            'isBalanced' => abs((float) $row->difference) < 0.01,
            'status' => $row->status,
            'note' => $row->note,
            'reconciledAt' => $row->reconciled_at?->toIso8601String(),
        ];
    }

    private function nextRun(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'harian' => $date->copy()->addDay(),
            'mingguan' => $date->copy()->addWeek(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    private function bookBalance(Company $company, int $accountId, string $period, ?int $branchId): float
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_lines.account_id', $accountId)
            ->whereBetween('journal_entries.date', [$start->toDateString(), $end->toDateString()])
            ->when($branchId !== null, fn ($scope) => $scope->where('journal_entries.branch_id', $branchId));

        return round((float) $query->sum(DB::raw('journal_lines.debit - journal_lines.credit')), 2);
    }

    private function assertOptionalCategory(Company $company, ?int $categoryId): void
    {
        if ($categoryId !== null && ExpenseCategory::query()
            ->where('company_id', $company->id)
            ->whereKey($categoryId)
            ->doesntExist()
        ) {
            throw ValidationException::withMessages([
                'expense_category_id' => 'Kategori tidak ada.',
            ]);
        }
    }

    private function assertSalvage(array $validated): void
    {
        if ((float) ($validated['salvage_value'] ?? 0) > (float) $validated['acquisition_cost']) {
            throw ValidationException::withMessages([
                'salvage_value' => 'Nilai residu tidak boleh lebih besar dari harga perolehan.',
            ]);
        }
    }

    private function assertAccountParent(Company $company, int $parentId, int $ignoreId): void
    {
        $cursor = Account::query()->where('company_id', $company->id)->whereKey($parentId)->first()
            ?? throw ValidationException::withMessages(['parent_id' => 'Akun induk tidak ada.']);

        $guard = 0;
        while ($cursor !== null && $guard < 100) {
            if ($cursor->id === $ignoreId) {
                throw ValidationException::withMessages(['parent_id' => 'Relasi akun induk membentuk lingkaran.']);
            }
            $cursor = $cursor->parent_id === null
                ? null
                : Account::query()->where('company_id', $company->id)->whereKey($cursor->parent_id)->first();
            $guard++;
        }
    }

    /** @return array<string, mixed> */
    private function accountRules(Company $company, ?int $ignoreId = null): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounts', 'code')->where('company_id', $company->id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['aset', 'liabilitas', 'ekuitas', 'pendapatan', 'beban'])],
            'parent_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    private function budgetRules(Company $company): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'expense_category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    private function recurringRules(Company $company): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'expense_category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', 'string', Rule::in(['tunai', 'transfer'])],
            'description' => ['required', 'string', 'max:500'],
            'frequency' => ['required', 'string', Rule::in(RecurringExpense::FREQUENCIES)],
            'next_run_at' => ['required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function assetRules(Company $company): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['sometimes', 'numeric', 'min:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['sometimes', 'string', Rule::in(['aktif', 'dilepas'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    private function reconciliationRules(Company $company): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $company->id)],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $company->id)],
            'period' => ['required', 'date_format:Y-m'],
            'statement_balance' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
