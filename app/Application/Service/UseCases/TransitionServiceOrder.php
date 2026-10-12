<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\ServiceOrder;
use Illuminate\Validation\ValidationException;

final class TransitionServiceOrder
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
    ) {}

    public function process(ServiceOrder $order, ?int $assigneeId): ServiceOrderDTO
    {
        $this->guard($order, 'baru');

        $order->status = 'diproses';
        $order->assignee_id = $assigneeId;

        return ServiceOrderDTO::fromModel($this->orders->save($order));
    }

    public function cancel(ServiceOrder $order): ServiceOrderDTO
    {
        if (! in_array($order->status, ['baru', 'diproses'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Order status '.$order->status.' tidak bisa dibatalkan.',
            ]);
        }

        $order->status = 'dibatalkan';

        return ServiceOrderDTO::fromModel($this->orders->save($order));
    }

    private function guard(ServiceOrder $order, string $from): void
    {
        if ($order->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'Order harus berstatus '.$from.' (saat ini: '.$order->status.').',
            ]);
        }
    }
}
