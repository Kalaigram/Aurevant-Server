<?php

declare(strict_types=1);

namespace App\Domain\Branch\Repositories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

interface BranchRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Branch> */
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?Branch;

    public function findByCodeForCompany(Company $company, string $code): ?Branch;

    public function createForCompany(Company $company, array $attributes): Branch;

    public function update(Branch $branch, array $attributes): Branch;

    public function delete(Branch $branch): void;
}
