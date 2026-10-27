<?php

declare(strict_types=1);

namespace App\Domain\Stock\Services;

use App\Domain\Stock\Exceptions\InsufficientStockException;

/**
 * Matematika stok: moving average + larangan stok negatif.
 * Murni (tanpa Laravel), bisa di-unit-test langsung.
 */
final class StockLedger
{
    public const TYPE_IN = ['pembelian', 'transfer_masuk', 'retur', 'void'];

    public const TYPE_OUT = ['penjualan', 'transfer_keluar', 'pemakaian_bahan', 'retur_supplier'];

    public const TYPE_ADJUST = ['penyesuaian', 'opname'];

    public function apply(float $balance, float $avgCost, string $type, float $qty, ?float $unitCost = null): StockResult
    {
        if (in_array($type, self::TYPE_IN, true)) {
            $newBalance = $balance + $qty;
            // retur/void tanpa harga: avg dipertahankan
            $newAvgCost = $unitCost === null
                ? $avgCost
                : ($newBalance > 0
                    ? ($balance * $avgCost + $qty * (float) $unitCost) / $newBalance
                    : (float) $unitCost);

            return new StockResult($qty, $newBalance, $newAvgCost, null);
        }

        if (in_array($type, self::TYPE_OUT, true)) {
            $newBalance = $balance - $qty;
            $this->guardNegative($newBalance);

            return new StockResult(-$qty, $newBalance, $avgCost, $qty * $avgCost);
        }

        // penyesuaian: qty boleh positif maupun negatif
        $newBalance = $balance + $qty;
        $this->guardNegative($newBalance);

        $newAvgCost = $qty > 0 && $unitCost !== null && $newBalance > 0
            ? ($balance * $avgCost + $qty * $unitCost) / $newBalance
            : $avgCost;

        return new StockResult($qty, $newBalance, $newAvgCost, null);
    }

    private function guardNegative(float $newBalance): void
    {
        if ($newBalance < 0) {
            throw new InsufficientStockException('Stok tidak mencukupi.');
        }
    }
}
