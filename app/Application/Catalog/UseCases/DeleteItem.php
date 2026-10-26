<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

final class DeleteItem
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Item $item): void
    {
        $imagePath = $item->image_path;

        $this->items->delete($item);

        if ($imagePath !== null) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
