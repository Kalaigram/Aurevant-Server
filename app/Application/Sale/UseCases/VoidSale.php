<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Application\Audit\Audit;
use App\Application\Ledger\LedgerPoster;
use App\Application\Notify\Notifier;
use App\Application\Report\UseCases\RebuildDailySummary;
use App\Application\Sale\DTOs\SaleReturnDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Approval\Services\ApprovalLedger;
use App\Domain\Sale\Repositories\SaleRepositoryInterface;
use App\Domain\Sale\Repositories\SaleReturnRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VoidSale
{
    public function __construct(
        private SaleRepositoryInterface $sales,
        private SaleReturnRepositoryInterface $returns,
        private RecordStockMovement $recordMovement,
        private RebuildDailySummary $rebuildSummary,
        private LedgerPoster $ledgerPoster,
    ) {}

    public function __invoke(Company $company, Branch $branch, Sale $sale, string $reason): SaleReturnDTO
    {
        if ($sale->status !== 'lunas') {
            throw ValidationException::withMessages([
                'sale' => 'Nota status '.$sale->status.' tidak bisa divoid.',
            ]);
        }

        $sale->loadMissing('shift');
        if ($sale->shift === null || $sale->shift->status !== 'terbuka') {
            throw ValidationException::withMessages([
                'sale' => 'Shift sudah ditutup, gunakan retur.',
            ]);
        }

        return DB::transaction(function () use ($company, $branch, $sale, $reason) {
            $sale->loadMissing('items.item');

            foreach ($sale->items as $line) {
                if ($line->item->type === 'barang' && (bool) $line->item->track_stock) {
                    ($this->recordMovement)(
                        $company, $branch, $line->item_id, 'void', (float) $line->qty, null,
                        'Void '.$sale->number,
                    );
                }
            }

            $sale->status = 'void';
            $sale->save();

            Audit::record(
                $company, $branch, 'sale.void', 'sale', $sale->id,
                ['status' => 'lunas'],
                ['status' => 'void', 'reason' => $reason],
            );
            Notifier::toPermissioned(
                $company, ['transaksi.void'], 'sale.void',
                'Nota '.$sale->number.' divoid.', null, '/sales/'.$sale->id, auth()->id(),
            );

            foreach (array_unique([$sale->created_at->toDateString(), now()->toDateString()]) as $date) {
                ($this->rebuildSummary)($branch, $date);
            }

            $retur = $this->returns->createWithItems([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'kind' => 'void',
                'reason' => $reason,
                'refund_method' => null,
                'refund_amount' => $sale->grand_total,
                'status' => 'disetujui',
            ], []);

            ($this->ledgerPoster)->forVoid($sale, $retur->id);

            ApprovalLedger::resolve(
                $company, 'sale', $sale->id, 'sale.void', auth()->user(), true, $reason,
            );

            return SaleReturnDTO::fromModel($retur);
        });
    }
}
