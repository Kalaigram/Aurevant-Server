<?php

declare(strict_types=1);

namespace App\Application\Sale\DTOs;

use App\Models\SalePayment;

final readonly class SalePaymentDTO
{
    public function __construct(
        public string $method,
        public string $amount,
        public ?string $tendered,
        public ?string $reference,
    ) {}

    public static function fromModel(SalePayment $payment): self
    {
        $str = fn ($value) => $value === null ? null : (string) $value;

        return new self(
            method: $payment->method,
            amount: (string) $payment->amount,
            tendered: $str($payment->tendered),
            reference: $payment->reference,
        );
    }
}
