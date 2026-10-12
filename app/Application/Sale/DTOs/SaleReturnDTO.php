<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\SaleReturn;

final readonly class SaleReturnDTO
{
    public function __construct(
        public int $id,
        public int $saleId,
        public string $kind,
        public string $reason,
        public ?string $refundMethod,
        public string $refundAmount,
        public string $status,
        /** @var list<SaleReturnItemDTO> */
        public array $items,
    ) {}

    public static function fromModel(SaleReturn $retur): self
    {
        return new self(
            id: $retur->id,
            saleId: $retur->sale_id,
            kind: $retur->kind,
            reason: $retur->reason,
            refundMethod: $retur->refund_method,
            refundAmount: (string) $retur->refund_amount,
            status: $retur->status,
            items: $retur->relationLoaded('items')
                ? $retur->items->map(fn ($item) => SaleReturnItemDTO::fromModel($item))->all()
                : [],
        );
    }
}
