<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Audit\Audit;
use App\Application\Catalog\DTOs\ItemPriceDTO;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Models\Branch;
use App\Models\Item;

final class SetItemPrice
{
    public function __construct(
        private ItemPriceRepositoryInterface $prices,
    ) {}

    public function __invoke(Item $item, Branch $branch, string $price): ItemPriceDTO
    {
        $before = $this->prices->findForItemBranch($item->id, $branch->id);
        $dto = ItemPriceDTO::fromModel(
            $this->prices->upsert($item, $branch, $price)
        );

        Audit::record(
            $item->company, $branch, 'price.set', 'item', $item->id,
            ['price' => $before === null ? null : (string) $before->price],
            ['price' => $dto->price],
        );

        return $dto;
    }
}
