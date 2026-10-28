<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Company;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CustomerEloquentRepository implements CustomerRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator
    {
        return Customer::query()
            ->where('company_id', $company->id)
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($customer) => $customer
                    ->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Customer
    {
        return Customer::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function profileForCompany(Company $company, Customer $customer): array
    {
        $sales = DB::table('sales')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['lunas', 'retur'])
            ->selectRaw('COUNT(*) AS transactions, COALESCE(SUM(grand_total), 0) AS gross, MAX(created_at) AS last_purchase')
            ->first();

        $refunds = (float) DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.company_id', $company->id)
            ->where('sales.customer_id', $customer->id)
            ->sum('sale_returns.refund_amount');

        $receivables = DB::table('receivables')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->selectRaw('SUM(CASE WHEN amount > paid_amount THEN 1 ELSE 0 END) AS open_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END), 0) AS outstanding')
            ->first();

        $orders = DB::table('service_orders')
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status IN ('baru', 'diproses', 'selesai') THEN 1 ELSE 0 END) AS active")
            ->first();

        $gross = (float) $sales->gross;
        $transactions = (int) $sales->transactions;
        $net = round($gross - $refunds, 2);

        $refundTotals = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_amount) AS refund_total')
            ->groupBy('sale_id');

        $recentSales = DB::table('sales')
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->leftJoinSub($refundTotals, 'refunds', 'refunds.sale_id', '=', 'sales.id')
            ->where('sales.company_id', $company->id)
            ->where('sales.customer_id', $customer->id)
            ->whereIn('sales.status', ['lunas', 'retur'])
            ->orderByDesc('sales.created_at')
            ->limit(10)
            ->get([
                'sales.id',
                'sales.number',
                'sales.status',
                'sales.grand_total',
                'sales.created_at',
                'branches.name AS branch_name',
                DB::raw('COALESCE(refunds.refund_total, 0) AS refund_total'),
            ])
            ->map(function ($row): array {
                $refund = (float) $row->refund_total;
                $total = (float) $row->grand_total;

                return [
                    'id' => (int) $row->id,
                    'number' => (string) $row->number,
                    'status' => (string) $row->status,
                    'branchName' => (string) $row->branch_name,
                    'grandTotal' => $total,
                    'refund' => $refund,
                    'net' => round($total - $refund, 2),
                    'createdAt' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
                ];
            })
            ->all();

        $itemTotals = DB::table('service_order_items')
            ->selectRaw('service_order_id, COALESCE(SUM(qty * price), 0) AS total')
            ->groupBy('service_order_id');

        $recentOrders = DB::table('service_orders')
            ->join('branches', 'branches.id', '=', 'service_orders.branch_id')
            ->leftJoinSub($itemTotals, 'items', 'items.service_order_id', '=', 'service_orders.id')
            ->where('service_orders.company_id', $company->id)
            ->where('service_orders.customer_id', $customer->id)
            ->orderByDesc('service_orders.created_at')
            ->limit(10)
            ->get([
                'service_orders.id',
                'service_orders.status',
                'service_orders.deposit_amount',
                'service_orders.scheduled_at',
                'service_orders.note',
                'service_orders.created_at',
                'branches.name AS branch_name',
                DB::raw('COALESCE(items.total, 0) AS total'),
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'branchName' => (string) $row->branch_name,
                'depositAmount' => (float) $row->deposit_amount,
                'total' => (float) $row->total,
                'scheduledAt' => $row->scheduled_at
                    ? CarbonImmutable::parse($row->scheduled_at)->toIso8601String()
                    : null,
                'createdAt' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
                'note' => $row->note,
            ])
            ->all();

        $recentReceivables = DB::table('receivables')
            ->join('branches', 'branches.id', '=', 'receivables.branch_id')
            ->where('receivables.company_id', $company->id)
            ->where('receivables.customer_id', $customer->id)
            ->orderByRaw('CASE WHEN receivables.amount > receivables.paid_amount THEN 0 ELSE 1 END')
            ->orderBy('receivables.due_at')
            ->limit(10)
            ->get([
                'receivables.id',
                'receivables.amount',
                'receivables.paid_amount',
                'receivables.due_at',
                'branches.name AS branch_name',
            ])
            ->map(function ($row): array {
                $amount = (float) $row->amount;
                $paid = (float) $row->paid_amount;
                $remaining = round($amount - $paid, 2);

                return [
                    'id' => (int) $row->id,
                    'branchName' => (string) $row->branch_name,
                    'amount' => $amount,
                    'paidAmount' => $paid,
                    'remaining' => $remaining,
                    'dueAt' => $row->due_at,
                    'isPaid' => $remaining <= 0,
                ];
            })
            ->all();

        return [
            'customer' => $customer,
            'stats' => [
                'transactions' => $transactions,
                'grossSpend' => $gross,
                'refunds' => $refunds,
                'netSpend' => $net,
                'avgTicket' => $transactions > 0 ? round($net / $transactions, 2) : 0.0,
                'lastPurchaseAt' => $sales->last_purchase
                    ? CarbonImmutable::parse($sales->last_purchase)->toIso8601String()
                    : null,
                'receivablesOutstanding' => (float) $receivables->outstanding,
                'receivablesOpen' => (int) $receivables->open_count,
                'serviceOrders' => (int) $orders->total,
                'activeServiceOrders' => (int) $orders->active,
            ],
            'sales' => $recentSales,
            'serviceOrders' => $recentOrders,
            'receivables' => $recentReceivables,
        ];
    }

    public function createForCompany(Company $company, array $attributes): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
