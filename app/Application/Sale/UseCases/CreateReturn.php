<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Application\Report\UseCases\RebuildDailySummary;
use App\Application\Sale\DTOs\SaleReturnDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Sale\Repositories\SaleReturnRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateReturn
{
    public function __construct(
        private SaleReturnRepositoryInterface $returns,
        private RecordStockMovement $recordMovement,
        private RebuildDailySummary $rebuildSummary,
        private LedgerPoster $ledgerPoster,
    ) {}

    /**
     * @param  list<array{sale_item_id: int, qty: float, condition: string}>  $lines
     */
    public function __invoke(
        Company $company,
        Branch $branch,
        Sale $sale,
        array $lines,
        string $reason,
        string $refundMethod,
    ): SaleReturnDTO {
        if ($sale->status !== 'lunas') {
            throw ValidationException::withMessages([
                'sale' => 'Nota status '.$sale->status.' tidak bisa diretur.',
            ]);
        }

        return DB::transaction(function () use ($company, $branch, $sale, $lines, $reason, $refundMethod) {
            $sale->loadMissing(['items.item', 'returns.items']);

            $alreadyReturned = [];
            foreach ($sale->returns as $retur) {
                foreach ($retur->items as $ri) {
                    $alreadyReturned[$ri->sale_item_id] = ($alreadyReturned[$ri->sale_item_id] ?? 0) + (float) $ri->qty;
                }
            }

            $rows = [];
            $refundTotal = 0.0;

            foreach ($lines as $line) {
                $saleItem = $sale->items->firstWhere('id', (int) $line['sale_item_id']);
                if ($saleItem === null) {
                    throw ValidationException::withMessages([
                        'items' => 'Baris nota '.$line['sale_item_id'].' bukan milik nota ini.',
                    ]);
                }

                $qty = (float) $line['qty'];
                $remaining = (float) $saleItem->qty - ($alreadyReturned[$saleItem->id] ?? 0);
                if ($qty <= 0 || round($remaining - $qty, 3) < 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty retur item '.$saleItem->item->code.' melebihi sisa '.$remaining.'.',
                    ]);
                }

                $refund = round((float) $saleItem->line_total * $qty / (float) $saleItem->qty, 2);
                $refundTotal += $refund;

                if ($line['condition'] === 'layak'
                    && $saleItem->item->type === 'barang'
                    && (bool) $saleItem->item->track_stock
                ) {
                    ($this->recordMovement)(
                        $company, $branch, $saleItem->item_id, 'retur', $qty, null,
                        'Retur '.$sale->number,
                    );
                }

                $rows[] = [
                    'sale_item_id' => $saleItem->id,
                    'item_id' => $saleItem->item_id,
                    'qty' => $qty,
                    'condition' => $line['condition'],
                    'refund_amount' => $refund,
                ];
            }

            $retur = $this->returns->createWithItems([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'kind' => 'retur',
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'refund_amount' => round($refundTotal, 2),
                'status' => 'disetujui',
            ], $rows);

            $fullyReturned = $sale->items->every(function ($saleItem) use ($alreadyReturned, $rows) {
                $returned = ($alreadyReturned[$saleItem->id] ?? 0);
                foreach ($rows as $row) {
                    if ($row['sale_item_id'] === $saleItem->id) {
                        $returned += $row['qty'];
                    }
                }

                return round((float) $saleItem->qty - $returned, 3) <= 0;
            });

            if ($fullyReturned) {
                $sale->status = 'retur';
                $sale->save();
            }

            Audit::record(
                $company, $branch, 'sale.retur', 'sale', $sale->id,
                ['status' => 'lunas'],
                ['status' => $sale->status, 'reason' => $reason, 'refund_method' => $refundMethod],
            );
            Notifier::toPermissioned(
                $company, ['retur.buat'], 'sale.retur',
                'Nota '.$sale->number.' diretur.', null, '/sales/'.$sale->id, auth()->id(),
            );

            foreach (array_unique([$sale->created_at->toDateString(), now()->toDateString()]) as $date) {
                ($this->rebuildSummary)($branch, $date);
            }

            ($this->ledgerPoster)->forReturn($sale, $retur);

            return SaleReturnDTO::fromModel($retur);
        });
    }
}
