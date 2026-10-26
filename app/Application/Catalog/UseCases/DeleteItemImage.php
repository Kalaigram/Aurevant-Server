<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

final class DeleteItemImage
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Item $item): ItemDTO
    {
        $oldPath = $item->image_path;
        $updated = $this->items->update($item, ['image_path' => null]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return ItemDTO::fromModel($updated);
    }
}
