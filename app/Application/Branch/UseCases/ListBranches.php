<?php

declare(strict_types=1);

namespace App\Application\Branch\UseCases;

use App\Application\Branch\DTOs\BranchDTO;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListBranches
{
    public function __construct(
        private BranchRepositoryInterface $branches,
    ) {}

    /** @return LengthAwarePaginator<int, BranchDTO> */
    public function __invoke(
        Company $company,
        int $perPage,
        ?string $q = null,
        ?string $type = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        return $this->branches->paginateForCompany($company, $perPage, $q, $type, $status)
            ->through(fn ($branch) => BranchDTO::fromModel($branch));
    }
}
