<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\ServiceOrder;
use Illuminate\Validation\ValidationException;

final class AddDeposit
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
    ) {}

    public function __invoke(ServiceOrder $order, float $amount): ServiceOrderDTO
    {
        if (! in_array($order->status, ['baru', 'diproses'], true)) {
            throw ValidationException::withMessages([
                'status' => 'DP hanya bisa ditambah saat order baru/diproses.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal DP harus lebih dari 0.',
            ]);
        }

        $order->deposit_amount = (float) $order->deposit_amount + $amount;

        return ServiceOrderDTO::fromModel($this->orders->save($order));
    }
}
