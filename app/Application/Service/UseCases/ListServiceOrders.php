<?php

declare(strict_types=1);

namespace App\Application\Service\UseCases;

use App\Application\Service\DTOs\ServiceOrderDTO;
use App\Domain\Service\Repositories\ServiceOrderRepositoryInterface;
use App\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListServiceOrders
{
    public function __construct(
        private ServiceOrderRepositoryInterface $orders,
    ) {}

    /** @return LengthAwarePaginator<int, ServiceOrderDTO> */
    public function __invoke(
        Branch $branch,
        int $perPage,
        ?string $status = null,
        ?string $q = null,
        ?string $scheduledFrom = null,
        ?string $scheduledTo = null,
    ): LengthAwarePaginator {
        return $this->orders->paginateForBranch($branch, $perPage, $status, $q, $scheduledFrom, $scheduledTo)
            ->through(fn ($order) => ServiceOrderDTO::fromModel($order));
    }
}
