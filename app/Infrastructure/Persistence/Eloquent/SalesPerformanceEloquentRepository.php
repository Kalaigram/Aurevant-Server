<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Report\Repositories\SalesPerformanceRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

final class SalesPerformanceEloquentRepository implements SalesPerformanceRepositoryInterface
{
    public function topProducts(Company $company, string $from, string $to, ?int $branchId, int $limit): array
    {
        $rows = DB::table('sales_items')
            ->join('sales', 'sales.id', '=', 'sales_items.sale_id')
            ->join('items', 'items.id', '=', 'sales_items.item_id')
            ->where('sales.company_id', $company->id)
            ->whereIn('sales.status', ['lunas', 'retur'])
            ->whereDate('sales.created_at', '>=', $from)
            ->whereDate('sales.created_at', '<=', $to)
            ->when($branchId !== null, fn ($query) => $query->where('sales.branch_id', $branchId))
            ->groupBy('items.id', 'items.code', 'items.name', 'items.unit')
            ->selectRaw('items.id AS item_id')
            ->selectRaw('items.code AS code')
            ->selectRaw('items.name AS name')
            ->selectRaw("COALESCE(items.unit, '') AS unit")
            ->selectRaw('COALESCE(SUM(sales_items.qty), 0) AS qty')
            ->selectRaw('COALESCE(SUM(sales_items.line_total), 0) AS revenue')
            ->selectRaw('COUNT(DISTINCT sales.id) AS transactions')
            ->selectRaw('COALESCE(SUM(sales_items.hpp_amount), 0) AS hpp')
            ->orderByRaw('SUM(sales_items.line_total) DESC')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'itemId' => (int) $row->item_id,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'unit' => (string) $row->unit,
            'qty' => (float) $row->qty,
            'revenue' => (float) $row->revenue,
            'transactions' => (int) $row->transactions,
            'hpp' => (float) $row->hpp,
        ])->all();
    }
}
