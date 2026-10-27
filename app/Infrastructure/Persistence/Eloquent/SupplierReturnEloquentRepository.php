<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\SupplierReturnRepositoryInterface;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnItem;
use Illuminate\Pagination\LengthAwarePaginator;

final class SupplierReturnEloquentRepository implements SupplierReturnRepositoryInterface
{
    public function paginateForOrder(PurchaseOrder $order, int $perPage): LengthAwarePaginator
    {
        return SupplierReturn::query()
            ->with('items.item')
            ->where('purchase_order_id', $order->id)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function returnedQuantities(array $receiptItemIds): array
    {
        if ($receiptItemIds === []) {
            return [];
        }

        return SupplierReturnItem::query()
            ->whereIn('goods_receipt_item_id', $receiptItemIds)
            ->groupBy('goods_receipt_item_id')
            ->selectRaw('goods_receipt_item_id, SUM(qty) as total_qty')
            ->pluck('total_qty', 'goods_receipt_item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    public function createWithItems(
        PurchaseOrder $order,
        GoodsReceipt $receipt,
        array $attributes,
        array $items,
    ): SupplierReturn {
        $retur = SupplierReturn::query()->create([
            'company_id' => $order->company_id,
            'purchase_order_id' => $order->id,
            'goods_receipt_id' => $receipt->id,
            ...$attributes,
        ]);
        $retur->items()->createMany($items);

        return $retur->refresh()->load('items.item');
    }
}
