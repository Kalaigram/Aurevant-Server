<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;

final class CreateOpname
{
    public function __construct(
        private StockOpnameRepositoryInterface $opnames,
        private ItemRepositoryInterface $items,
        private StockBalanceRepositoryInterface $balances,
    ) {}

    /**
     * @param  list<array{item_id: int, qty_counted: float}>  $lines
     */
    public function __invoke(Company $company, Branch $branch, array $lines, ?string $note): StockOpnameDTO
    {
        $rows = [];
        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);
            $balance = $this->balances->find($branch->id, $item->id);
            $rows[] = [
                'item_id' => $item->id,
                'qty_system' => (float) ($balance?->qty ?? 0),
                'qty_counted' => $line['qty_counted'],
            ];
        }

        return StockOpnameDTO::fromModel(
            $this->opnames->createWithItems($company, $branch, [
                'status' => 'draft',
                'note' => $note,
            ], $rows)
        );
    }
}
