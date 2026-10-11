<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockTransferDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;

final class CreateTransfer
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
        private ItemRepositoryInterface $items,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float}>  $lines
     */
    public function __invoke(Company $company, Branch $from, Branch $to, array $lines, ?string $note): StockTransferDTO
    {
        $rows = [];
        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);
            $rows[] = ['item_id' => $item->id, 'qty_requested' => $line['qty']];
        }

        return StockTransferDTO::fromModel(
            $this->transfers->createWithItems($company, [
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => 'draft',
                'note' => $note,
            ], $rows)
        );
    }
}
