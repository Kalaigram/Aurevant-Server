<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

final class CreateServiceOrder
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
        private ItemRepositoryInterface $items,
        private ItemPriceRepositoryInterface $prices,
        private CustomerRepositoryInterface $customers,
    ) {}

    /**
     * @param  list<array{item_id: int, qty: float}>  $lines
     */
    public function __invoke(
        Company $company,
        Branch $branch,
        array $lines,
        ?int $customerId,
        ?string $scheduledAt,
        ?string $note,
    ): ServiceOrderDTO {
        if ($customerId !== null) {
            $this->customers->findForCompany($company, $customerId) ?? abort(404);
        }

        $rows = [];
        foreach ($lines as $line) {
            $item = $this->items->findForCompany($company, (int) $line['item_id']) ?? abort(404);

            if ($item->type !== 'jasa') {
                throw ValidationException::withMessages([
                    'items' => 'Item '.$item->code.' bukan jasa.',
                ]);
            }

            $price = $this->prices->findForItemBranch($item->id, $branch->id);
            if ($price === null) {
                throw ValidationException::withMessages([
                    'items' => 'Harga jasa '.$item->code.' belum diatur di cabang ini.',
                ]);
            }

            $rows[] = [
                'item_id' => $item->id,
                'qty' => $line['qty'],
                'price' => $price->price,
                'commission_amount' => 0,
            ];
        }

        return ServiceOrderDTO::fromModel(
            $this->orders->createWithItems($company, $branch, [
                'customer_id' => $customerId,
                'status' => 'baru',
                'deposit_amount' => 0,
                'scheduled_at' => $scheduledAt,
                'note' => $note,
            ], $rows)
        );
    }
}
