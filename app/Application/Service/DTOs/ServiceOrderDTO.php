<?php

declare(strict_types=1);

namespace App\Application\Service\DTOs;

use App\Models\ServiceOrder;

final readonly class ServiceOrderDTO
{
    public function __construct(
        public int $id,
        public int $branchId,
        public ?int $customerId,
        public ?int $assigneeId,
        public ?int $saleId,
        public string $status,
        public string $depositAmount,
        public ?string $scheduledAt,
        public ?string $note,
        /** @var list<ServiceOrderItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(ServiceOrder $order): self
    {
        return new self(
            id: $order->id,
            branchId: $order->branch_id,
            customerId: $order->customer_id,
            assigneeId: $order->assignee_id,
            saleId: $order->sale_id,
            status: $order->status,
            depositAmount: (string) $order->deposit_amount,
            scheduledAt: $order->scheduled_at?->toISOString(),
            note: $order->note,
            items: $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => ServiceOrderItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
