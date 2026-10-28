<?php

declare(strict_types=1);

namespace App\Application\Hr\DTOs;

use App\Models\LeaveBalance;
use App\Models\User;

final readonly class LeaveBalanceDTO
{
    public function __construct(
        public ?int $id,
        public int $userId,
        public string $userName,
        public int $year,
        public string $entitlement,
        public string $carryover,
        public string $used,
        public string $available,
    ) {}

    public static function fromModel(LeaveBalance $balance): self
    {
        return new self(
            id: $balance->id,
            userId: $balance->user_id,
            userName: $balance->relationLoaded('user') ? (string) $balance->user?->name : '',
            year: $balance->year,
            entitlement: (string) $balance->entitlement,
            carryover: (string) $balance->carryover,
            used: (string) $balance->used,
            available: number_format($balance->available(), 2, '.', ''),
        );
    }

    public static function empty(User $user, int $year, float $entitlement = 12.0): self
    {
        return new self(
            id: null,
            userId: $user->id,
            userName: $user->name,
            year: $year,
            entitlement: number_format($entitlement, 2, '.', ''),
            carryover: '0.00',
            used: '0.00',
            available: number_format($entitlement, 2, '.', ''),
        );
    }
}
