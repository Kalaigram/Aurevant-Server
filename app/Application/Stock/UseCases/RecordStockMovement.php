<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockMovementDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Domain\Stock\Services\StockLedger;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

final class RecordStockMovement
{
    public function __construct(
        private ItemRepositoryInterface $items,
        private StockBalanceRepositoryInterface $balances,
        private StockMovementRepositoryInterface $movements,
        private StockLedger $ledger,
    ) {}

    public function __invoke(
        Company $company,
        Branch $branch,
        int $itemId,
        string $type,
        float $qty,
        ?float $unitCost,
        ?string $note,
    ): StockMovementDTO {
        $item = $this->items->findForCompany($company, $itemId) ?? abort(404);

        return DB::transaction(function () use ($company, $branch, $item, $type, $qty, $unitCost, $note) {
            $balance = $this->balances->findLocked($branch->id, $item->id);

            $result = $this->ledger->apply(
                (float) ($balance?->qty ?? 0),
                (float) ($balance?->avg_cost ?? 0),
                $type,
                $qty,
                $unitCost,
            );

            if ($balance === null) {
                $balance = $this->balances->create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'item_id' => $item->id,
                    'qty' => 0,
                    'avg_cost' => 0,
                ]);
            }

            $balance->qty = $result->newBalance;
            $balance->avg_cost = $result->newAvgCost;
            $this->balances->save($balance);

            $movement = $this->movements->record([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'item_id' => $item->id,
                'type' => $type,
                'qty' => $result->qtyDelta,
                'unit_cost' => $unitCost,
                'hpp_amount' => $result->hppAmount,
                'balance_after' => $result->newBalance,
                'note' => $note,
            ]);

            return StockMovementDTO::fromModel($movement);
        });
    }
}
