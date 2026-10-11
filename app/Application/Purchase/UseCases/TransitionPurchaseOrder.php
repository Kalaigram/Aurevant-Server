<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Purchase\DTOs\PurchaseOrderDTO;
use App\Domain\Purchase\Repositories\PurchaseOrderRepositoryInterface;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

final class TransitionPurchaseOrder
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $orders,
    ) {}

    public function submit(PurchaseOrder $order): PurchaseOrderDTO
    {
        return $this->move($order, 'draft', 'diajukan');
    }

    public function approve(PurchaseOrder $order): PurchaseOrderDTO
    {
        return $this->move($order, 'diajukan', 'disetujui');
    }

    public function order(PurchaseOrder $order): PurchaseOrderDTO
    {
        return $this->move($order, 'disetujui', 'dipesan');
    }

    public function cancel(PurchaseOrder $order): PurchaseOrderDTO
    {
        if (! in_array($order->status, ['draft', 'diajukan', 'disetujui', 'dipesan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'PO status '.$order->status.' tidak bisa dibatalkan.',
            ]);
        }

        $order->status = 'dibatalkan';

        return PurchaseOrderDTO::fromModel($this->orders->save($order));
    }

    private function move(PurchaseOrder $order, string $from, string $to): PurchaseOrderDTO
    {
        if ($order->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'PO harus berstatus '.$from.' (saat ini: '.$order->status.').',
            ]);
        }

        $order->status = $to;

        return PurchaseOrderDTO::fromModel($this->orders->save($order));
    }
}
