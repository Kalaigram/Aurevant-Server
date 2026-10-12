<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Services;

/** PTKP + tarif progresif UU HPP. Murni, bisa di-unit-test langsung. */
final class PtkpTable
{
    /** @return list<string> */
    public static function statuses(): array
    {
        return ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];
    }

    public static function amount(string $status): float
    {
        return match ($status) {
            'TK/0' => 54000000,
            'TK/1', 'K/0' => 58500000,
            'TK/2', 'K/1' => 63000000,
            'TK/3', 'K/2' => 67500000,
            'K/3' => 72000000,
            default => 54000000,
        };
    }

    /** PPh 21 sebulan: setahunkan bruto, kurangi biaya jabatan, kurangi PTKP, progresif, bagi 12. */
    public static function monthlyTax(float $monthlyTaxable, string $status): float
    {
        if ($monthlyTaxable <= 0) {
            return 0.0;
        }

        $annual = $monthlyTaxable * 12;
        $net = $annual - min($annual * 0.05, 6000000);
        $pkp = floor(max(0.0, $net - self::amount($status)) / 1000) * 1000;

        $tax = self::progressive($pkp);

        return round($tax / 12, 2);
    }

    private static function progressive(float $pkp): float
    {
        $bands = [
            [60000000, 0.05],
            [250000000, 0.15],
            [500000000, 0.25],
            [5000000000, 0.30],
            [PHP_FLOAT_MAX, 0.35],
        ];

        $tax = 0.0;
        $prev = 0.0;

        foreach ($bands as [$limit, $rate]) {
            if ($pkp <= $prev) {
                break;
            }

            $tax += (min($pkp, $limit) - $prev) * $rate;
            $prev = $limit;
        }

        return $tax;
    }
}
