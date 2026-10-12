<?php

declare(strict_types=1);

namespace App\Domain\Report\Repositories;

use App\Models\Branch;
use App\Models\DailyBranchSummary;

interface SummaryRepositoryInterface
{
    /** @return list<DailyBranchSummary> */
    public function listForBranches(array $branchIds, string $from, string $to): array;

    public function upsert(Branch $branch, string $date, array $attributes): DailyBranchSummary;
}
