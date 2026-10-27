<?php

declare(strict_types=1);

namespace App\Application\Ledger;

use App\Domain\Ledger\Repositories\LedgerRepositoryInterface;
use App\Domain\Ledger\Services\DefaultChartOfAccounts;
use App\Domain\Ledger\Services\JournalPoster;
use App\Models\Company;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SupplierBill;
use App\Models\SupplierReturn;

/** Aturan posting tiap dokumen. Idempoten per sumber. */
final class LedgerPoster
{
    public function __construct(
        private LedgerRepositoryInterface $ledger,
        private JournalPoster $poster,
    ) {}

    public function forSale(Sale $sale): void
    {
        $sale->loadMissing(['items.item', 'payments']);
        $this->ledger->ensureDefaultAccounts($sale->company);
        $codes = DefaultChartOfAccounts::paymentAccounts();
        $today = $sale->created_at->toDateString();

        $lines = [];
        $revBarang = 0.0;
        $revJasa = 0.0;
        $hpp = 0.0;

        foreach ($sale->payments as $payment) {
            $lines[] = ['account' => $codes[$payment->method] ?? '1110', 'debit' => (float) $payment->amount];
        }

        foreach ($sale->items as $line) {
            $gross = (float) $line->price * (float) $line->qty;
            $disc = (float) $line->discount_percent > 0
                ? $gross * (float) $line->discount_percent / 100
                : (float) $line->discount_amount;
            $net = $gross - $disc;
            $tax = (float) $line->line_total - $net;

            if ($line->item->type === 'jasa') {
                $revJasa += $net;
            } else {
                $revBarang += $net;
            }

            $hpp += (float) ($line->hpp_amount ?? 0);
        }

        if ($revBarang > 0) {
            $lines[] = ['account' => '4100', 'credit' => round($revBarang, 2)];
        }
        if ($revJasa > 0) {
            $lines[] = ['account' => '4200', 'credit' => round($revJasa, 2)];
        }
        if ((float) $sale->tax_total > 0) {
            $lines[] = ['account' => '2130', 'credit' => (float) $sale->tax_total];
        }
        if ($hpp > 0) {
            $lines[] = ['account' => '5100', 'debit' => round($hpp, 2)];
            $lines[] = ['account' => '1310', 'credit' => round($hpp, 2)];
        }

        $this->poster->post(
            $sale->company, $sale->branch_id, $today, 'Nota '.$sale->number,
            'sale', $sale->id, $lines,
        );
    }

    public function forVoid(Sale $sale, int $returnId): void
    {
        $this->ledger->ensureDefaultAccounts($sale->company);
        $this->poster->reverse(
            $sale->company, $sale->branch_id, now()->toDateString(), 'Void '.$sale->number,
            'sale_void', $returnId, 'sale', $sale->id,
        );
    }

    public function forReturn(Sale $sale, SaleReturn $retur): void
    {
        $this->ledger->ensureDefaultAccounts($sale->company);
        $sale->loadMissing('items.item');
        $retur->loadMissing('items');

        $lines = [];
        foreach ($retur->items as $line) {
            $saleItem = $sale->items->firstWhere('id', $line->sale_item_id);
            if ($saleItem === null) {
                continue;
            }

            $refund = (float) $line->refund_amount;
            $lineTotal = (float) $saleItem->line_total;
            $gross = (float) $saleItem->price * (float) $saleItem->qty;
            $disc = (float) $saleItem->discount_percent > 0
                ? $gross * (float) $saleItem->discount_percent / 100
                : (float) $saleItem->discount_amount;
            $net = $gross - $disc;
            $tax = $lineTotal - $net;
            $taxPart = $lineTotal > 0 ? round($refund * $tax / $lineTotal, 2) : 0.0;
            $revPart = round($refund - $taxPart, 2);

            $account = $saleItem->item->type === 'jasa' ? '4200' : '4100';
            $lines[] = ['account' => $account, 'debit' => $revPart];
            if ($taxPart > 0) {
                $lines[] = ['account' => '2130', 'debit' => $taxPart];
            }

            if ($line->condition === 'layak' && $saleItem->item->type === 'barang' && (float) $saleItem->qty > 0) {
                $hppBack = round((float) ($saleItem->hpp_amount ?? 0) * (float) $line->qty / (float) $saleItem->qty, 2);
                if ($hppBack > 0) {
                    $lines[] = ['account' => '1310', 'debit' => $hppBack];
                    $lines[] = ['account' => '5100', 'credit' => $hppBack];
                }
            }
        }

        $lines[] = ['account' => $retur->refund_method === 'transfer' ? '1120' : '1110', 'credit' => (float) $retur->refund_amount];

        $this->poster->post(
            $sale->company, $sale->branch_id, now()->toDateString(), 'Retur '.$sale->number,
            'sale_return', $retur->id, $lines,
        );
    }

    public function forReceipt(PurchaseOrder $order, int $receiptId, float $totalValue): void
    {
        $this->ledger->ensureDefaultAccounts($order->company);
        $this->poster->post(
            $order->company, $order->branch_id, now()->toDateString(), 'Penerimaan PO #'.$order->id,
            'po_receipt', $receiptId,
            [
                ['account' => '1310', 'debit' => round($totalValue, 2)],
                ['account' => '2110', 'credit' => round($totalValue, 2)],
            ]
        );
    }

    public function forSupplierReturn(PurchaseOrder $order, SupplierReturn $retur, float $inventoryValue): void
    {
        $this->ledger->ensureDefaultAccounts($order->company);

        $total = (float) $retur->total;
        $inventoryValue = round($inventoryValue, 2);
        $difference = round($total - $inventoryValue, 2);

        $lines = [
            ['account' => '2110', 'debit' => $total],
            ['account' => '1310', 'credit' => $inventoryValue],
        ];
        if ($difference > 0) {
            $lines[] = ['account' => '5100', 'credit' => $difference];
        } elseif ($difference < 0) {
            $lines[] = ['account' => '5100', 'debit' => abs($difference)];
        }

        $this->poster->post(
            $order->company, $order->branch_id, now()->toDateString(), 'Retur supplier PO #'.$order->id,
            'supplier_return', $retur->id, $lines,
        );
    }

    public function forPayrollAccrue(PayrollPeriod $period, float $gross): void
    {
        $this->ledger->ensureDefaultAccounts($period->company);
        $this->poster->post(
            $period->company, null, now()->toDateString(), 'Beban gaji '.$period->name,
            'payroll_accrue', $period->id,
            [
                ['account' => '6100', 'debit' => round($gross, 2)],
                ['account' => '2140', 'credit' => round($gross, 2)],
            ]
        );
    }

    public function forPayrollPay(PayrollPeriod $period, float $gross): void
    {
        $this->poster->post(
            $period->company, null, now()->toDateString(), 'Bayar gaji '.$period->name,
            'payroll_pay', $period->id,
            [
                ['account' => '2140', 'debit' => round($gross, 2)],
                ['account' => '1110', 'credit' => round($gross, 2)],
            ]
        );
    }

    public function forExpense(Company $company, Expense $expense): void
    {
        $this->ledger->ensureDefaultAccounts($company);
        $codes = DefaultChartOfAccounts::paymentAccounts();
        $this->poster->post(
            $company, $expense->branch_id, (string) $expense->date->toDateString(), 'Biaya: '.$expense->description,
            'expense', $expense->id,
            [
                ['account' => '6200', 'debit' => (float) $expense->amount],
                ['account' => $codes[$expense->method] ?? '1110', 'credit' => (float) $expense->amount],
            ]
        );
    }

    public function forBillPayment(Company $company, SupplierBill $bill, string $method, float $amount, int $paymentId): void
    {
        $this->ledger->ensureDefaultAccounts($company);
        $codes = DefaultChartOfAccounts::paymentAccounts();
        $this->poster->post(
            $company, $bill->order->branch_id, now()->toDateString(), 'Bayar hutang PO #'.$bill->purchase_order_id,
            'bill_payment', $paymentId,
            [
                ['account' => '2110', 'debit' => round($amount, 2)],
                ['account' => $codes[$method] ?? '1110', 'credit' => round($amount, 2)],
            ]
        );
    }

    public function forReceivable(Company $company, Receivable $receivable): void
    {
        $this->ledger->ensureDefaultAccounts($company);
        $this->poster->post(
            $company, $receivable->branch_id, now()->toDateString(), 'Piutang #'.$receivable->id,
            'receivable', $receivable->id,
            [
                ['account' => '1210', 'debit' => (float) $receivable->amount],
                ['account' => '4100', 'credit' => (float) $receivable->amount],
            ]
        );
    }

    public function forReceivablePayment(Company $company, Receivable $receivable, string $method, float $amount, int $paymentId): void
    {
        $codes = DefaultChartOfAccounts::paymentAccounts();
        $this->poster->post(
            $company, $receivable->branch_id, now()->toDateString(), 'Terima piutang #'.$receivable->id,
            'receivable_payment', $paymentId,
            [
                ['account' => $codes[$method] ?? '1110', 'debit' => round($amount, 2)],
                ['account' => '1210', 'credit' => round($amount, 2)],
            ]
        );
    }
}
