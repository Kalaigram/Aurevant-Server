<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface CompanyRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Company> */
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator;

    public function findBySlug(string $slug): ?Company;

    public function findById(int $id): ?Company;

    public function create(array $attributes): Company;

    /** @param array<string, mixed> $attributes */
    public function update(Company $company, array $attributes): Company;
}
