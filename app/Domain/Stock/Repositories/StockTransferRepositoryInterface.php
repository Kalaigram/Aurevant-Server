<?php

declare(strict_types=1);

namespace App\Domain\Stock\Repositories;

use App\Models\Company;
use App\Models\StockTransfer;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockTransferRepositoryInterface
{
    /** @return LengthAwarePaginator<int, StockTransfer> */
    public function paginateForCompany(Company $company, int $perPage, ?string $status = null): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?StockTransfer;

    public function createWithItems(Company $company, array $attributes, array $items): StockTransfer;

    public function save(StockTransfer $transfer): StockTransfer;
}
