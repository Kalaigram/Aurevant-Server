<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\GoodsReceiptRepositoryInterface;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

final class GoodsReceiptEloquentRepository implements GoodsReceiptRepositoryInterface
{
    public function paginateForOrder(PurchaseOrder $order, int $perPage): LengthAwarePaginator
    {
        return GoodsReceipt::query()
            ->with('items.item')
            ->where('purchase_order_id', $order->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function createWithItems(PurchaseOrder $order, array $attributes, array $items): GoodsReceipt
    {
        $receipt = GoodsReceipt::query()->create([
            'company_id' => $order->company_id,
            'purchase_order_id' => $order->id,
            ...$attributes,
        ]);
        $receipt->items()->createMany($items);

        return $receipt->refresh()->load('items.item');
    }
}
