<?php

declare(strict_types=1);

namespace App\Application\Finance\UseCases;

use App\Application\Finance\DTOs\ReceivableDTO;
use App\Application\Ledger\LedgerPoster;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Finance\Repositories\DebtRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Receivable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageReceivables
{
    public function __construct(
        private DebtRepositoryInterface $debts,
        private BranchRepositoryInterface $branches,
        private LedgerPoster $ledgerPoster,
    ) {}

    /** @return LengthAwarePaginator<int, ReceivableDTO> */
    public function list(Company $company, int $perPage, bool $unpaidOnly): LengthAwarePaginator
    {
        return $this->debts->paginateReceivables($company, $perPage, $unpaidOnly)
            ->through(fn ($receivable) => ReceivableDTO::fromModel($receivable));
    }

    public function record(Company $company, Branch $branch, array $attributes): ReceivableDTO
    {
        return DB::transaction(function () use ($company, $branch, $attributes) {
            $receivable = $this->debts->createReceivable($company, [
                'branch_id' => $branch->id,
                ...$attributes,
            ]);

            ($this->ledgerPoster)->forReceivable($company, $receivable);

            return ReceivableDTO::fromModel($receivable);
        });
    }

    public function collect(Company $company, Receivable $receivable, float $amount, string $method, ?string $paidAt, ?string $note): ReceivableDTO
    {
        if ($amount <= 0 || round($receivable->remaining() - $amount, 2) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Melebihi sisa piutang '.$receivable->remaining().'.',
            ]);
        }

        return DB::transaction(function () use ($company, $receivable, $amount, $method, $paidAt, $note) {
            $payment = $this->debts->payReceivable($receivable, [
                'amount' => $amount,
                'method' => $method,
                'paid_at' => $paidAt ?? now()->toDateString(),
                'note' => $note,
            ]);

            ($this->ledgerPoster)->forReceivablePayment($company, $receivable->refresh(), $method, $amount, $payment->id);

            return ReceivableDTO::fromModel($receivable->refresh()->load(['customer', 'payments']));
        });
    }

    /** @return array{buckets: array<string, float>, total: float} */
    public function aging(Company $company): array
    {
        $buckets = ['lancar' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'lebih_90' => 0.0];

        foreach ($this->debts->unpaidReceivables($company) as $receivable) {
            $outstanding = $receivable->remaining();
            $bucket = 'lancar';

            if ($receivable->due_at !== null && now()->greaterThan($receivable->due_at)) {
                $days = (int) now()->diffInDays($receivable->due_at, true);
                $bucket = match (true) {
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => 'lebih_90',
                };
            }

            $buckets[$bucket] = round($buckets[$bucket] + $outstanding, 2);
        }

        return ['buckets' => $buckets, 'total' => round(array_sum($buckets), 2)];
    }

    /** @return array{buckets: array<string, float>, total: float} */
    public function supplierAging(Company $company): array
    {
        $buckets = ['lancar' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'lebih_90' => 0.0];

        foreach ($this->debts->unpaidBills($company) as $bill) {
            $outstanding = $bill->remaining();
            $bucket = 'lancar';

            if ($bill->due_at !== null && now()->greaterThan($bill->due_at)) {
                $days = (int) now()->diffInDays($bill->due_at, true);
                $bucket = match (true) {
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => 'lebih_90',
                };
            }

            $buckets[$bucket] = round($buckets[$bucket] + $outstanding, 2);
        }

        return ['buckets' => $buckets, 'total' => round(array_sum($buckets), 2)];
    }
}
