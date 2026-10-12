<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\RoleDTO;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListRoles
{
    public function __construct(
        private RoleRepositoryInterface $roles,
    ) {}

    /** @return LengthAwarePaginator<int, RoleDTO> */
    public function __invoke(Company $company, int $perPage): LengthAwarePaginator
    {
        return $this->roles->paginateForCompany($company, $perPage)
            ->through(fn ($role) => RoleDTO::fromModel($role));
    }
}
