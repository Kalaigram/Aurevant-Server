<?php

declare(strict_types=1);

namespace App\Application\Purchase\UseCases;

use App\Application\Audit\Audit;
use App\Application\Notify\Notifier;
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
        $dto = $this->move($order, 'draft', 'diajukan');
        $this->trail($order, 'po.submit', 'draft', 'diajukan');
        Notifier::toPermissioned(
            $order->company, ['po.setujui'], 'po.diajukan',
            'PO #'.$order->id.' diajukan.', null, '/purchase-orders/'.$order->id, auth()->id(),
        );

        return $dto;
    }

    public function approve(PurchaseOrder $order): PurchaseOrderDTO
    {
        $dto = $this->move($order, 'diajukan', 'disetujui');
        $this->trail($order, 'po.approve', 'diajukan', 'disetujui');
        Notifier::toPermissioned(
            $order->company, ['po.setujui'], 'po.disetujui',
            'PO #'.$order->id.' disetujui.', null, '/purchase-orders/'.$order->id, auth()->id(),
        );

        return $dto;
    }

    public function order(PurchaseOrder $order): PurchaseOrderDTO
    {
        $dto = $this->move($order, 'disetujui', 'dipesan');
        $this->trail($order, 'po.order', 'disetujui', 'dipesan');

        return $dto;
    }

    public function cancel(PurchaseOrder $order): PurchaseOrderDTO
    {
        if (! in_array($order->status, ['draft', 'diajukan', 'disetujui', 'dipesan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'PO status '.$order->status.' tidak bisa dibatalkan.',
            ]);
        }

        $from = $order->status;
        $order->status = 'dibatalkan';

        $dto = PurchaseOrderDTO::fromModel($this->orders->save($order));
        $this->trail($order, 'po.cancel', $from, 'dibatalkan');

        return $dto;
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

    private function trail(PurchaseOrder $order, string $action, string $from, string $to): void
    {
        Audit::record(
            $order->company, $order->branch_id, $action, 'purchase_order', $order->id,
            ['status' => $from],
            ['status' => $to],
        );
    }
}
