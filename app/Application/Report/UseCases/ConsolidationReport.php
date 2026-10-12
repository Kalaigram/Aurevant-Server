<?php

declare(strict_types=1);

namespace App\Application\Report\UseCases;

use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Report\Repositories\SummaryRepositoryInterface;
use App\Models\Company;

final class ConsolidationReport
{
    public function __construct(
        private SummaryRepositoryInterface $summaries,
        private BranchRepositoryInterface $branches,
    ) {}

    /** @return array{branches: list<array<string, mixed>>, grand: array<string, float>} */
    public function __invoke(Company $company, string $from, string $to): array
    {
        $branches = $company->branches()->orderBy('code')->get();
        $ids = $branches->pluck('id')->all();
        $rows = $this->summaries->listForBranches($ids, $from, $to);

        $perBranch = [];
        foreach ($branches as $branch) {
            $perBranch[$branch->id] = [
                'branchId' => $branch->id,
                'branchCode' => $branch->code,
                'branchName' => $branch->name,
                'omzet' => 0.0, 'transactions' => 0, 'hpp' => 0.0,
                'refunds' => 0.0, 'net' => 0.0,
            ];
        }

        foreach ($rows as $row) {
            $slot = &$perBranch[$row->branch_id];
            $slot['omzet'] += (float) $row->omzet;
            $slot['transactions'] += $row->transactions;
            $slot['hpp'] += (float) $row->hpp_total;
            $slot['refunds'] += (float) $row->refund_total;
            $slot['net'] += (float) $row->net_omzet;
        }
        unset($slot);

        $grand = ['omzet' => 0.0, 'transactions' => 0, 'hpp' => 0.0, 'refunds' => 0.0, 'net' => 0.0];
        foreach ($perBranch as $slot) {
            foreach (array_keys($grand) as $key) {
                $grand[$key] += $slot[$key];
            }
        }

        return ['branches' => array_values($perBranch), 'grand' => $grand];
    }
}
