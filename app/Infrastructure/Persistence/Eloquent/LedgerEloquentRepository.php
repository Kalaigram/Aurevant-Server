<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Domain\Ledger\Services\DefaultChartOfAccounts;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class LedgerEloquentRepository implements LedgerRepositoryInterface
{
    public function findAccount(Company $company, string $code): ?Account
    {
        return Account::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->first();
    }

    public function accountsForCompany(Company $company): array
    {
        return Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get()
            ->all();
    }

    public function ensureDefaultAccounts(Company $company): void
    {
        foreach (DefaultChartOfAccounts::all() as $account) {
            Account::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                ['name' => $account['name'], 'type' => $account['type']]
            );
        }
    }

    public function existsForSource(Company $company, string $sourceType, int|string $sourceId): bool
    {
        return JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    public function isPeriodClosed(Company $company, int $year, int $month): bool
    {
        return AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'tutup')
            ->exists();
    }

    public function findEntry(Company $company, string $sourceType, int|string $sourceId): ?JournalEntry
    {
        return JournalEntry::query()
            ->with('lines')
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    public function createEntry(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        array $lines,
    ): JournalEntry {
        return $this->createEntryRaw($company, $branchId, $date, $memo, $sourceType, $sourceId, $lines);
    }

    public function createEntryRaw(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        array $lines,
    ): JournalEntry {
        return DB::transaction(function () use ($company, $branchId, $date, $memo, $sourceType, $sourceId, $lines) {
            $entry = JournalEntry::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'date' => $date,
                'memo' => $memo,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);
            $entry->lines()->createMany($lines);

            return $entry->refresh()->load('lines');
        });
    }

    public function paginateEntries(Company $company, int $perPage, ?string $from, ?string $to): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with('lines.account')
            ->where('company_id', $company->id)
            ->when($from !== null, fn ($query) => $query->where('date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function totalsByAccount(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $company->id)
            ->whereBetween('journal_entries.date', [$from, $to])
            ->when($branchId !== null, fn ($query) => $query->where('journal_entries.branch_id', $branchId))
            ->groupBy('accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.code, accounts.name, COALESCE(SUM(journal_lines.debit), 0) AS debit, COALESCE(SUM(journal_lines.credit), 0) AS credit')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ])
            ->all();
    }
}
