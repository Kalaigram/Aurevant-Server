<?php

declare(strict_types=1);

namespace App\Application\Company\UseCases;

use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCompanies
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {}

    /** @return LengthAwarePaginator<int, CompanyDTO> */
    public function paginated(User $user, int $perPage): LengthAwarePaginator
    {
        return $this->companies->paginateForUser($user, $perPage)
            ->through(fn ($company) => CompanyDTO::fromModel($company));
    }
}
