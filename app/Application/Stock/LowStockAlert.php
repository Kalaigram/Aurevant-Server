<?php

declare(strict_types=1);

namespace App\Application\Stock;

use App\Application\Notify\Notifier;
use App\Models\Branch;
use App\Models\Company;
use App\Models\StockBalance;

final class LowStockAlert
{
    public function check(Company $company, Branch $branch, StockBalance $balance, float $previousQty): void
    {
        if ($balance->min_stock === null) {
            return;
        }

        $minimum = (float) $balance->min_stock;
        $qty = (float) $balance->qty;

        if ($previousQty <= $minimum || $qty > $minimum) {
            return;
        }

        $item = $balance->item;

        if ($item === null) {
            return;
        }

        $unit = $item->unit ?? 'pcs';

        Notifier::toPermissioned(
            $company,
            ['stok.opname'],
            'stok.menipis',
            'Stok menipis: '.$item->name,
            $branch->name.': sisa '.$this->format($qty).' '.$unit.' (minimum '.$this->format($minimum).' '.$unit.').',
            '/stock',
        );
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }
}
