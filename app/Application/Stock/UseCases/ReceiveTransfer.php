<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Audit\Audit;
use App\Application\Stock\DTOs\StockTransferDTO;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Domain\Stock\Services\StockLedger;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceiveTransfer
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private StockBalanceRepositoryInterface $balances,
        private StockMovementRepositoryInterface $movements,
        private StockLedger $ledger,
    ) {}

    /**
     * @param  list<array{item_id: int, qty_received: float}>  $lines
     */
    public function __invoke(StockTransfer $transfer, array $lines): StockTransferDTO
    {
        if ($transfer->status !== 'dikirim') {
            throw ValidationException::withMessages([
                'status' => 'Transfer harus berstatus dikirim (saat ini: '.$transfer->status.').',
            ]);
        }

        $receivedByItem = [];
        foreach ($lines as $line) {
            $receivedByItem[(int) $line['item_id']] = (float) $line['qty_received'];
        }

        return DB::transaction(function () use ($transfer, $receivedByItem) {
            foreach ($transfer->items as $line) {
                if (! array_key_exists($line->item_id, $receivedByItem)) {
                    throw ValidationException::withMessages([
                        'items' => 'Item '.$line->item_id.' belum ada qty diterima.',
                    ]);
                }

                $qtyReceived = $receivedByItem[$line->item_id];
                if ($qtyReceived < 0 || $qtyReceived > (float) $line->qty_shipped) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty diterima item '.$line->item_id.' harus 0 sampai '.$line->qty_shipped.'.',
                    ]);
                }

                $balance = $this->balances->findLocked($transfer->to_branch_id, $line->item_id);
                $result = $this->ledger->apply(
                    (float) ($balance?->qty ?? 0),
                    (float) ($balance?->avg_cost ?? 0),
                    'transfer_masuk',
                    $qtyReceived,
                    (float) $line->cost_per_unit,
                );

                if ($balance === null) {
                    $balance = $this->balances->create([
                        'company_id' => $transfer->company_id,
                        'branch_id' => $transfer->to_branch_id,
                        'item_id' => $line->item_id,
                        'qty' => 0,
                        'avg_cost' => 0,
                    ]);
                }

                $balance->qty = $result->newBalance;
                $balance->avg_cost = $result->newAvgCost;
                $this->balances->save($balance);

                $this->movements->record([
                    'company_id' => $transfer->company_id,
                    'branch_id' => $transfer->to_branch_id,
                    'item_id' => $line->item_id,
                    'type' => 'transfer_masuk',
                    'qty' => $result->qtyDelta,
                    'unit_cost' => (float) $line->cost_per_unit,
                    'hpp_amount' => null,
                    'balance_after' => $result->newBalance,
                    'note' => 'Transfer #'.$transfer->id,
                ]);

                $line->qty_received = $qtyReceived;
                $line->save();
            }

            $transfer->status = 'diterima';
            $transfer->received_at = now();

            $dto = StockTransferDTO::fromModel($this->transfers->save($transfer));
            Audit::record(
                $transfer->company, $transfer->to_branch_id, 'stock.transfer.receive', 'stock_transfer', $transfer->id,
                ['status' => 'dikirim'],
                ['status' => 'diterima'],
            );

            return $dto;
        });
    }
}
