<?php

declare(strict_types=1);

namespace App\Domain\Stock\Services;

/** Hasil hitung ledger: murni, tanpa database. */
final readonly class StockResult
{
    public function __construct(
        public float $qtyDelta,
        public float $newBalance,
        public float $newAvgCost,
        public ?float $hppAmount,
    ) {}
}
