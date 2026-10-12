<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteServiceOrder
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
        private RecordStockMovement $recordMovement,
    ) {}

    public function __invoke(ServiceOrder $order): ServiceOrderDTO
    {
        if ($order->status !== 'diproses') {
            throw ValidationException::withMessages([
                'status' => 'Order harus berstatus diproses (saat ini: '.$order->status.').',
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('items.item.consumables');

            foreach ($order->items as $line) {
                $line->commission_amount = $this->commission($line);
                $line->save();

                foreach ($line->item->consumables as $recipe) {
                    ($this->recordMovement)(
                        $order->company,
                        $order->branch,
                        $recipe->material_item_id,
                        'pemakaian_bahan',
                        (float) $recipe->qty * (float) $line->qty,
                        null,
                        'Jasa order #'.$order->id,
                    );
                }
            }

            $order->status = 'selesai';

            return ServiceOrderDTO::fromModel($this->orders->save($order));
        });
    }

    private function commission(object $line): float
    {
        $net = (float) $line->price * (float) $line->qty;
        $type = $line->item->commission_type;
        $value = (float) $line->item->commission_value;

        if ($type === 'persen' && $value > 0) {
            return round($net * $value / 100, 2);
        }

        if ($type === 'nominal' && $value > 0) {
            return round($value * (float) $line->qty, 2);
        }

        return 0.0;
    }
}
