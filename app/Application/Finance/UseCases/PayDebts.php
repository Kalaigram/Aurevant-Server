<?php

declare(strict_types=1);

namespace App\Application\Finance\UseCases;

use App\Application\Audit\Audit;
use App\Application\Finance\DTOs\BillDTO;
use App\Application\Ledger\LedgerPoster;
use App\Domain\Finance\Repositories\DebtRepositoryInterface;
use App\Models\Company;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayDebts
{
    public function __construct(
        private DebtRepositoryInterface $debts,
        private LedgerPoster $ledgerPoster,
    ) {}

    public function payBill(Company $company, SupplierBill $bill, float $amount, string $method, ?string $paidAt, ?string $note): BillDTO
    {
        if ($amount <= 0 || round($bill->remaining() - $amount, 2) < 0) {
            throw ValidationException::withMessages([
                'amount' => 'Melebihi sisa tagihan '.$bill->remaining().'.',
            ]);
        }

        return DB::transaction(function () use ($company, $bill, $amount, $method, $paidAt, $note) {
            $payment = $this->debts->payBill($bill, [
                'amount' => $amount,
                'method' => $method,
                'paid_at' => $paidAt ?? now()->toDateString(),
                'note' => $note,
            ]);

            ($this->ledgerPoster)->forBillPayment($company, $bill->refresh(), $method, $amount, $payment->id);

            Audit::record($company, $bill->order->branch_id, 'hutang.bayar', 'supplier_bill', $bill->id, null, ['amount' => $amount]);

            return BillDTO::fromModel($bill->refresh()->load('order.supplier'));
        });
    }
}
