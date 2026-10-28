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
        public ?string $customerName,
        public ?int $assigneeId,
        public ?string $assigneeName,
        public ?int $saleId,
        public ?int $packageId,
        public ?string $packageName,
        public string $status,
        public string $depositAmount,
        public ?string $scheduledAt,
        public ?string $note,
        public string $total,
        public ?string $createdAt,
        /** @var list<ServiceOrderItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(ServiceOrder $order): self
    {
        $items = $order->relationLoaded('items')
            ? $order->items->map(fn ($item) => ServiceOrderItemDTO::fromModel($item))->all()
            : [];

        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item->qty * (float) $item->price;
        }

        return new self(
            id: $order->id,
            branchId: $order->branch_id,
            customerId: $order->customer_id,
            customerName: $order->relationLoaded('customer') ? $order->customer?->name : null,
            assigneeId: $order->assignee_id,
            assigneeName: $order->relationLoaded('assignee') ? $order->assignee?->name : null,
            saleId: $order->sale_id,
            packageId: $order->service_package_id,
            packageName: $order->relationLoaded('servicePackage') ? $order->servicePackage?->name : null,
            status: $order->status,
            depositAmount: (string) $order->deposit_amount,
            scheduledAt: $order->scheduled_at?->toISOString(),
            note: $order->note,
            total: number_format($total, 2, '.', ''),
            createdAt: $order->created_at?->toISOString(),
            items: $items,
        );
    }
}
