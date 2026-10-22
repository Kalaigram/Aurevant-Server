<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockMovementDTO;
use App\Domain\Stock\Repositories\StockMovementRepositoryInterface;
use App\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListStockMovements
{
    public function __construct(
        private StockMovementRepositoryInterface $movements,
    ) {}

    /** @return LengthAwarePaginator<int, StockMovementDTO> */
    public function __invoke(Branch $branch, int $perPage, ?int $itemId = null, ?string $q = null): LengthAwarePaginator
    {
        return $this->movements->historyForBranch($branch, $perPage, $itemId, $q)
            ->through(fn ($movement) => StockMovementDTO::fromModel($movement));
    }
}
