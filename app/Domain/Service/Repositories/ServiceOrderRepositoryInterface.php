<?php

declare(strict_types=1);

namespace App\Domain\Service\Repositories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ServiceOrder;
use Illuminate\Pagination\LengthAwarePaginator;

interface ServiceOrderRepositoryInterface
{
    /** @return LengthAwarePaginator<int, ServiceOrder> */
    public function paginateForBranch(Branch $branch, int $perPage, ?string $status = null, ?string $q = null): LengthAwarePaginator;

    public function findForBranch(Branch $branch, int $id): ?ServiceOrder;

    public function createWithItems(Company $company, Branch $branch, array $attributes, array $items): ServiceOrder;

    public function save(ServiceOrder $order): ServiceOrder;
}
