<?php

declare(strict_types=1);

namespace App\Application\Report\DTOs;

use App\Models\DailyBranchSummary;

final readonly class DailySummaryDTO
{
    public function __construct(
        public int $branchId,
        public string $date,
        public string $omzet,
        public int $transactions,
        public string $hppTotal,
        public string $discountTotal,
        public string $taxTotal,
        public string $cashTunai,
        public string $refundTotal,
        public string $netOmzet,
    ) {}

    public static function fromModel(DailyBranchSummary $summary): self
    {
        return new self(
            branchId: $summary->branch_id,
            date: $summary->date->toDateString(),
            omzet: (string) $summary->omzet,
            transactions: $summary->transactions,
            hppTotal: (string) $summary->hpp_total,
            discountTotal: (string) $summary->discount_total,
            taxTotal: (string) $summary->tax_total,
            cashTunai: (string) $summary->cash_tunai,
            refundTotal: (string) $summary->refund_total,
            netOmzet: (string) $summary->net_omzet,
        );
    }
}
