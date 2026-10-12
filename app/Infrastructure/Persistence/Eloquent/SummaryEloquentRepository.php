<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Report\Repositories\SummaryRepositoryInterface;
use App\Models\Branch;
use App\Models\DailyBranchSummary;

final class SummaryEloquentRepository implements SummaryRepositoryInterface
{
    public function listForBranches(array $branchIds, string $from, string $to): array
    {
        return DailyBranchSummary::query()
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('branch_id')
            ->get()
            ->all();
    }

    public function upsert(Branch $branch, string $date, array $attributes): DailyBranchSummary
    {
        return DailyBranchSummary::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'date' => $date],
            ['company_id' => $branch->company_id, ...$attributes],
        );
    }
}
