<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListStockOpnames
{
    public function __construct(
        private StockOpnameRepositoryInterface $opnames,
    ) {}

    /** @return LengthAwarePaginator<int, StockOpnameDTO> */
    public function __invoke(Branch $branch, int $perPage): LengthAwarePaginator
    {
        return $this->opnames->paginateForBranch($branch, $perPage)
            ->through(fn ($opname) => StockOpnameDTO::fromModel($opname));
    }
}
