<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Audit\Audit;
use App\Application\Notify\Notifier;
use App\Application\Stock\DTOs\StockTransferDTO;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Domain\Stock\Services\StockLedger;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShipTransfer
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private StockBalanceRepositoryInterface $balances,
        private StockMovementRepositoryInterface $movements,
        private StockLedger $ledger,
    ) {}

    public function __invoke(StockTransfer $transfer): StockTransferDTO
    {
        if ($transfer->status !== 'disetujui') {
            throw ValidationException::withMessages([
                'status' => 'Transfer harus berstatus disetujui (saat ini: '.$transfer->status.').',
            ]);
        }

        return DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $line) {
                $balance = $this->balances->findLocked($transfer->from_branch_id, $line->item_id);
                $result = $this->ledger->apply(
                    (float) ($balance?->qty ?? 0),
                    (float) ($balance?->avg_cost ?? 0),
                    'transfer_keluar',
                    (float) $line->qty_requested,
                );

                if ($balance === null) {
                    $balance = $this->balances->create([
                        'company_id' => $transfer->company_id,
                        'branch_id' => $transfer->from_branch_id,
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
                    'branch_id' => $transfer->from_branch_id,
                    'item_id' => $line->item_id,
                    'type' => 'transfer_keluar',
                    'qty' => $result->qtyDelta,
                    'unit_cost' => null,
                    'hpp_amount' => $result->hppAmount,
                    'balance_after' => $result->newBalance,
                    'note' => 'Transfer #'.$transfer->id,
                ]);

                $line->qty_shipped = $line->qty_requested;
                $line->cost_per_unit = $balance->avg_cost;
                $line->save();
            }

            $transfer->status = 'dikirim';
            $transfer->shipped_at = now();

            $dto = StockTransferDTO::fromModel($this->transfers->save($transfer));
            Audit::record(
                $transfer->company, $transfer->from_branch_id, 'stock.transfer.ship', 'stock_transfer', $transfer->id,
                ['status' => 'disetujui'],
                ['status' => 'dikirim'],
            );
            Notifier::toPermissioned(
                $transfer->company, ['stok.transfer'], 'transfer.dikirim',
                'Transfer #'.$transfer->id.' dikirim.', null, '/transfers/'.$transfer->id, auth()->id(),
            );

            return $dto;
        });
    }
}
