<?php

declare(strict_types=1);

namespace App\Domain\Sale\Repositories;

use App\Models\Branch;
use App\Models\CashShift;

interface CashShiftRepositoryInterface
{
    public function openShift(Branch $branch, int $userId, string $openingCash): CashShift;

    public function findOpenForBranch(Branch $branch): ?CashShift;

    public function save(CashShift $shift): CashShift;
}
