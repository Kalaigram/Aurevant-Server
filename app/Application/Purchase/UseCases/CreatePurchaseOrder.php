<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\PurchaseOrderDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;

final class CreatePurchaseOrder
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $orders,
        private ItemRepositoryInterface $items,
    ) {}

    /**
     * @param  list<array{item_id: int, qty_ordered: float, unit_price: float}>  $lines
     */
    public function __invoke(Company $company, Branch $branch, Supplier $supplier, array $lines, ?string $note): PurchaseOrderDTO
    {
        $rows = [];
        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);
            $rows[] = [
                'item_id' => $item->id,
                'qty_ordered' => $line['qty_ordered'],
                'qty_received' => 0,
                'unit_price' => $line['unit_price'],
            ];
        }

        return PurchaseOrderDTO::fromModel(
            $this->orders->createWithItems($company, [
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => 'draft',
                'note' => $note,
            ], $rows)
        );
    }
}
