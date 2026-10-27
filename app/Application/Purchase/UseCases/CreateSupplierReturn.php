<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Application\Purchase\DTOs\SupplierReturnDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierBillRepositoryInterface;
use App\Domain\Purchase\Repositories\SupplierReturnRepositoryInterface;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSupplierReturn
{
    public function __construct(
        private SupplierReturnRepositoryInterface $returns,
        private GoodsReceiptRepositoryInterface $receipts,
        private SupplierBillRepositoryInterface $bills,
        private RecordStockMovement $recordMovement,
        private LedgerPoster $ledgerPoster,
    ) {}

    /**
     * @param  list<array{goods_receipt_item_id: int, qty: float}>  $lines
     */
    public function __invoke(
        PurchaseOrder $order,
        GoodsReceipt $receipt,
        array $lines,
        string $reason,
        ?string $note,
    ): SupplierReturnDTO {
        if (! in_array($order->status, ['dipesan', 'selesai'], true)) {
            throw ValidationException::withMessages([
                'status' => 'PO status '.$order->status.' tidak bisa diretur ke supplier.',
            ]);
        }

        return DB::transaction(function () use ($order, $receipt, $lines, $reason, $note) {
            $lockedReceipt = $this->receipts->findForOrder($order, $receipt->id, true);
            if ($lockedReceipt === null) {
                throw ValidationException::withMessages([
                    'receipt' => 'Penerimaan tidak ditemukan pada PO ini.',
                ]);
            }

            $bill = $this->bills->findForOrder($order->company, $order, true);
            if ($bill === null) {
                throw ValidationException::withMessages([
                    'bill' => 'Tagihan supplier belum terbentuk untuk PO ini.',
                ]);
            }

            $receiptItems = $lockedReceipt->items->keyBy('id');
            $alreadyReturned = $this->returns->returnedQuantities($receiptItems->keys()->all());
            $seen = [];
            $rows = [];
            $total = 0.0;

            foreach ($lines as $line) {
                $receiptItemId = (int) $line['goods_receipt_item_id'];
                $receiptItem = $receiptItems->get($receiptItemId);

                if ($receiptItem === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Baris penerimaan '.$receiptItemId.' bukan milik penerimaan ini.',
                    ]);
                }

                if (isset($seen[$receiptItemId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Item penerimaan yang sama tidak boleh muncul lebih dari sekali.',
                    ]);
                }
                $seen[$receiptItemId] = true;

                $qty = (float) $line['qty'];
                $remaining = round(
                    (float) $receiptItem->qty_received - ($alreadyReturned[$receiptItemId] ?? 0.0),
                    3,
                );

                if ($qty <= 0 || round($remaining - $qty, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty retur '.$receiptItem->item?->name.' melebihi sisa '.$remaining.'.',
                    ]);
                }

                $unitCost = (float) $receiptItem->unit_cost;
                $subtotal = round($qty * $unitCost, 2);
                $total += $subtotal;

                $rows[] = [
                    'goods_receipt_item_id' => $receiptItem->id,
                    'item_id' => $receiptItem->item_id,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages([
                    'items' => 'Tidak ada item yang diretur.',
                ]);
            }

            $total = round($total, 2);
            if (round($bill->remaining() - $total, 2) < 0) {
                throw ValidationException::withMessages([
                    'items' => 'Nilai retur melebihi sisa hutang supplier ('.$bill->remaining().').',
                ]);
            }

            $inventoryValue = 0.0;
            foreach ($rows as $row) {
                $movement = ($this->recordMovement)(
                    $order->company,
                    $order->branch,
                    $row['item_id'],
                    'retur_supplier',
                    (float) $row['qty'],
                    (float) $row['unit_cost'],
                    'Retur supplier PO #'.$order->id,
                );
                $inventoryValue += (float) ($movement->hppAmount ?? 0);
            }

            $billBefore = (float) $bill->amount;
            $bill->amount = round($billBefore - $total, 2);
            $this->bills->save($bill);

            $retur = $this->returns->createWithItems(
                $order,
                $lockedReceipt,
                ['total' => $total, 'reason' => $reason, 'note' => $note],
                $rows,
            );

            Audit::record(
                $order->company, $order->branch_id, 'po.retur', 'purchase_order', $order->id,
                ['bill_amount' => (string) $billBefore],
                [
                    'bill_amount' => (string) $bill->amount,
                    'return_total' => (string) $total,
                    'receipt_id' => $lockedReceipt->id,
                    'reason' => $reason,
                ],
            );
            Notifier::toPermissioned(
                $order->company, ['po.setujui'], 'po.retur',
                'PO #'.$order->id.' diretur ke supplier.',
                'Nilai retur '.number_format($total, 2, ',', '.').' mengurangi stok dan hutang.',
                '/purchase-orders/'.$order->id,
                auth()->id(),
            );

            ($this->ledgerPoster)->forSupplierReturn($order, $retur, round($inventoryValue, 2));

            return SupplierReturnDTO::fromModel($retur);
        });
    }
}
