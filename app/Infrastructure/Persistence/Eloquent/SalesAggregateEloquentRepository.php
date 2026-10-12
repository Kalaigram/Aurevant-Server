<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Report\Repositories\SalesAggregateRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class SalesAggregateEloquentRepository implements SalesAggregateRepositoryInterface
{
    public function aggregateBranchDay(int $branchId, string $date): array
    {
        $sales = DB::table('sales')
            ->where('branch_id', $branchId)
            ->where('status', 'lunas')
            ->whereDate('created_at', $date)
            ->selectRaw('COALESCE(SUM(grand_total), 0) AS omzet, COUNT(*) AS transactions, COALESCE(SUM(discount_total), 0) AS discount, COALESCE(SUM(tax_total), 0) AS tax')
            ->first();

        $hpp = DB::table('sales_items')
            ->join('sales', 'sales.id', '=', 'sales_items.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', 'lunas')
            ->whereDate('sales.created_at', $date)
            ->sum('sales_items.hpp_amount');

        $cash = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.branch_id', $branchId)
            ->where('sales.status', 'lunas')
            ->where('sale_payments.method', 'tunai')
            ->whereDate('sales.created_at', $date)
            ->sum('sale_payments.amount');

        $refunds = DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.branch_id', $branchId)
            ->whereDate('sale_returns.created_at', $date)
            ->sum('sale_returns.refund_amount');

        return [
            'omzet' => (float) $sales->omzet,
            'transactions' => (int) $sales->transactions,
            'hpp' => (float) $hpp,
            'discount' => (float) $sales->discount,
            'tax' => (float) $sales->tax,
            'cash' => (float) $cash,
            'refunds' => (float) $refunds,
        ];
    }
}
