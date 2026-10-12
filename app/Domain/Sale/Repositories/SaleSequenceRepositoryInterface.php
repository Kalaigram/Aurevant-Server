<?php

declare(strict_types=1);

namespace App\Domain\Sale\Repositories;

use App\Models\Branch;

interface SaleSequenceRepositoryInterface
{
    public function nextNumber(Branch $branch, string $date): int;
}
