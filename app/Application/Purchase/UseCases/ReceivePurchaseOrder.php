<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Application\Purchase\DTOs\PurchaseOrderDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierBillRepositoryInterface;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceivePurchaseOrder
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $orders,
        private GoodsReceiptRepositoryInterface $receipts,
        private SupplierBillRepositoryInterface $bills,
        private RecordStockMovement $recordMovement,
        private LedgerPoster $ledgerPoster,
    ) {}

    /**
     * @param  list<array{item_id: int, qty_received: float, unit_cost: float}>  $lines
     */
    public function __invoke(PurchaseOrder $order, array $lines, ?string $dueAt, ?string $note): PurchaseOrderDTO
    {
        if ($order->status !== 'dipesan') {
            throw ValidationException::withMessages([
                'status' => 'PO harus berstatus dipesan (saat ini: '.$order->status.').',
            ]);
        }

        return DB::transaction(function () use ($order, $lines, $dueAt, $note) {
            $byItem = [];
            foreach ($lines as $line) {
                $byItem[(int) $line['item_id']] = $line;
            }

            $receiptRows = [];
            $billTotal = 0.0;

            foreach ($order->items as $poItem) {
                if (! array_key_exists($poItem->item_id, $byItem)) {
                    continue;
                }

                $qty = (float) $byItem[$poItem->item_id]['qty_received'];
                $cost = (float) $byItem[$poItem->item_id]['unit_cost'];
                $remaining = (float) $poItem->qty_ordered - (float) $poItem->qty_received;

                if ($qty <= 0 || round($remaining - $qty, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty diterima item '.$poItem->item_id.' melebihi sisa '.$remaining.'.',
                    ]);
                }

                ($this->recordMovement)(
                    $order->company,
                    $order->branch,
                    $poItem->item_id,
                    'pembelian',
                    $qty,
                    $cost,
                    'PO #'.$order->id,
                );

                $poItem->qty_received = (float) $poItem->qty_received + $qty;
                $poItem->save();

                $receiptRows[] = [
                    'item_id' => $poItem->item_id,
                    'qty_received' => $qty,
                    'unit_cost' => $cost,
                ];
                $billTotal += $qty * $cost;
            }

            if ($receiptRows === []) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada item PO yang diterima.',
                ]);
            }

            $receipt = $this->receipts->createWithItems($order, ['due_at' => $dueAt, 'note' => $note], $receiptRows);

            $bill = $this->bills->findOrCreateForOrder($order->company, $order);
            $bill->amount = (float) $bill->amount + $billTotal;
            if ($dueAt !== null) {
                $bill->due_at = $dueAt;
            }
            $this->bills->save($bill);

            $complete = $order->items->every(
                fn ($poItem) => round((float) $poItem->qty_ordered - (float) $poItem->qty_received, 3) <= 0
            );
            if ($complete) {
                $order->status = 'selesai';
            }

            $dto = PurchaseOrderDTO::fromModel($this->orders->save($order));
            Audit::record(
                $order->company, $order->branch_id, 'po.receive', 'purchase_order', $order->id,
                ['status' => 'dipesan'],
                ['status' => $order->status, 'bill_amount' => (string) $bill->amount],
            );
            Notifier::toPermissioned(
                $order->company, ['po.setujui'], 'po.diterima',
                'PO #'.$order->id.' ada penerimaan barang.', null, '/purchase-orders/'.$order->id, auth()->id(),
            );
            ($this->ledgerPoster)->forReceipt($order, $receipt->id, $billTotal);

            return $dto;
        });
    }
}
