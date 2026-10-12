<?php

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\DTOs\LeaveDTO;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListLeaves
{
    public function __construct(
        private LeaveRepositoryInterface $leaves,
    ) {}

    /** @return LengthAwarePaginator<int, LeaveDTO> */
    public function __invoke(Company $company, int $perPage, ?int $userId, ?string $status): LengthAwarePaginator
    {
        return $this->leaves->paginateForCompany($company, $perPage, $userId, $status)
            ->through(fn ($leave) => LeaveDTO::fromModel($leave));
    }
}
