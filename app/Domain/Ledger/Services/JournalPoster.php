<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

/** Posting jurnal berpasangan. Menolak tidak seimbang. Idempoten per sumber. */
final class JournalPoster
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
    ) {}

    /**
     * @param  list<array{account: string, debit?: float, credit?: float}>  $lines
     */
    public function post(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        array $lines,
    ): void {
        if ($this->ledger->existsForSource($company, $sourceType, $sourceId)) {
            return;
        }

        $this->assertPeriodOpen($company, $date);

        $debit = 0.0;
        $credit = 0.0;
        $resolved = [];

        foreach ($lines as $line) {
            $account = $this->ledger->findAccount($company, $line['account']);
            if ($account === null) {
                throw ValidationException::withMessages([
                    'akun' => 'Akun '.$line['account'].' belum ada.',
                ]);
            }

            $debit += $line['debit'] ?? 0;
            $credit += $line['credit'] ?? 0;
            $resolved[] = [
                'account_id' => $account->id,
                'debit' => round($line['debit'] ?? 0, 2),
                'credit' => round($line['credit'] ?? 0, 2),
            ];
        }

        if (abs(round($debit - $credit, 2)) > 0.009 || round($debit, 2) <= 0) {
            throw ValidationException::withMessages([
                'jurnal' => 'Jurnal tidak seimbang.',
            ]);
        }

        $this->ledger->createEntry($company, $branchId, $date, $memo, $sourceType, $sourceId, $resolved);
    }

    public function reverse(
        Company $company,
        ?int $branchId,
        string $date,
        string $memo,
        string $sourceType,
        int|string $sourceId,
        string $originalSourceType,
        int|string $originalSourceId,
    ): void {
        $original = $this->ledger->findEntry($company, $originalSourceType, $originalSourceId);
        if ($original === null) {
            return;
        }

        $mirrored = $original->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
        ])->all();

        if ($this->ledger->existsForSource($company, $sourceType, $sourceId)) {
            return;
        }

        $this->assertPeriodOpen($company, $date);

        $this->ledger->createEntryRaw($company, $branchId, $date, $memo, $sourceType, $sourceId, $mirrored);
    }

    private function assertPeriodOpen(Company $company, string $date): void
    {
        $parsed = date_create_immutable($date);
        if ($parsed === false) {
            return;
        }

        $year = (int) $parsed->format('Y');
        $month = (int) $parsed->format('n');

        if ($this->ledger->isPeriodClosed($company, $year, $month)) {
            throw ValidationException::withMessages([
                'periode' => sprintf('Periode %04d-%02d sudah ditutup.', $year, $month),
            ]);
        }
    }
}
