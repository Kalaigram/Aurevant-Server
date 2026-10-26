<?php

declare(strict_types=1);

namespace App\Application\Catalog\UseCases;

use App\Application\Catalog\DTOs\ItemDTO;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreItemImage
{
    public function __construct(
        private ItemRepositoryInterface $items,
    ) {}

    public function __invoke(Item $item, UploadedFile $file): ItemDTO
    {
        $path = $file->store('item-images', 'public');

        if ($path === false) {
            abort(500, 'Gambar item gagal diunggah.');
        }

        $oldPath = $item->image_path;
        $updated = $this->items->update($item, ['image_path' => $path]);

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return ItemDTO::fromModel($updated);
    }
}
