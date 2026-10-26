<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Audit\Audit;
use App\Application\Stock\DTOs\StockBalanceDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;

final class SetStockMinimum
{
    public function __construct(
        private ItemRepositoryInterface $items,
        private StockBalanceRepositoryInterface $balances,
    ) {}

    public function __invoke(Company $company, Branch $branch, int $itemId, ?float $minStock): StockBalanceDTO
    {
        $item = $this->items->findForCompany($company, $itemId) ?? abort(404);
        $balance = $this->balances->find($branch->id, $item->id);

        if ($balance === null) {
            $balance = $this->balances->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'item_id' => $item->id,
                'qty' => 0,
                'avg_cost' => 0,
            ]);
        }

        $before = $balance->min_stock === null ? null : (string) $balance->min_stock;
        $balance->min_stock = $minStock;
        $saved = $this->balances->save($balance);

        Audit::record(
            $company,
            $branch,
            'stock.minimum.update',
            'stock_balance',
            $saved->id,
            ['min_stock' => $before],
            ['min_stock' => $saved->min_stock === null ? null : (string) $saved->min_stock],
        );

        return StockBalanceDTO::fromModel($saved->load('item'));
    }
}
