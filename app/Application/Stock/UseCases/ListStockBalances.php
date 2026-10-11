<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockBalanceDTO;
use App\Domain\Stock\Repositories\StockBalanceRepositoryInterface;
use App\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListStockBalances
{
    public function __construct(
        private StockBalanceRepositoryInterface $balances,
    ) {}

    /** @return LengthAwarePaginator<int, StockBalanceDTO> */
    public function __invoke(Branch $branch, int $perPage): LengthAwarePaginator
    {
        return $this->balances->paginateForBranch($branch, $perPage)
            ->through(fn ($balance) => StockBalanceDTO::fromModel($balance));
    }
}
