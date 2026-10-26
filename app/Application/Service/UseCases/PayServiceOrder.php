<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Sale\DTOs\SaleDTO;
use App\Application\Sale\UseCases\CreateSale;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class PayServiceOrder
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
        private CreateSale $createSale,
    ) {}

    /**
     * @param  list<array{method: string, amount: float, tendered?: float|null, reference?: string|null}>  $payments
     */
    public function __invoke(
        Company $company,
        Branch $branch,
        User $cashier,
        ServiceOrder $order,
        array $payments,
        ?string $idempotencyKey,
    ): SaleDTO {
        if ($order->status !== 'selesai') {
            throw ValidationException::withMessages([
                'status' => 'Order harus berstatus selesai (saat ini: '.$order->status.').',
            ]);
        }

        $order->loadMissing('items');

        $lines = [];
        $estimate = 0.0;
        foreach ($order->items as $line) {
            $lines[] = [
                'item_id' => $line->item_id,
                'qty' => (float) $line->qty,
                'price' => (float) $line->price,
            ];
            $estimate += (float) $line->price * (float) $line->qty;
        }

        $deposit = (float) $order->deposit_amount;
        if ($deposit > $estimate) {
            throw ValidationException::withMessages([
                'deposit' => 'DP melebihi total jasa.',
            ]);
        }

        $allPayments = $payments;
        if ($deposit > 0) {
            array_unshift($allPayments, [
                'method' => 'dp',
                'amount' => $deposit,
                'reference' => 'DP Order #'.$order->id,
            ]);
        }

        $result = ($this->createSale)(
            $company, $branch, $cashier, $lines, $allPayments, $idempotencyKey,
            $order->customer_id, $order->id, deductServiceConsumables: false,
        );

        $order->sale_id = $result['sale']->id;
        $order->status = 'dibayar';
        $this->orders->save($order);

        return $result['sale'];
    }
}
