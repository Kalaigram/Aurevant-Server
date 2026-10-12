<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Repositories;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface LedgerRepositoryInterface
{
    public function findAccount(Company $company, string $code): ?Account;

    /** @return list<Account> */
    public function accountsForCompany(Company $company): array;

    public function ensureDefaultAccounts(Company $company): void;

    public function existsForSource(Company $company, string $sourceType, int|string $sourceId): bool;

    public function isPeriodClosed(Company $company, int $year, int $month): bool;

    public function findEntry(Company $company, string $sourceType, int|string $sourceId): ?JournalEntry;

    /** @param list<array{account_id: int, debit: float, credit: float}> $lines */
    public function createEntry(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        array $lines,
    ): JournalEntry;

    /** @param list<array{account_id: int, debit: float, credit: float}> $lines */
    public function createEntryRaw(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        array $lines,
    ): JournalEntry;

    /** @return LengthAwarePaginator<int, JournalEntry> */
    public function paginateEntries(Company $company, int $perPage, ?string $from, ?string $to): LengthAwarePaginator;

    /** @return list<array{code: string, name: string, debit: float, credit: float}> */
    public function totalsByAccount(Company $company, string $from, string $to, ?int $branchId = null): array;
}
