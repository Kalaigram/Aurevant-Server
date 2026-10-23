<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CompanyRepositoryInterface
{
    /** @return Collection<int, Company> */
    public function all(): Collection;

    /** @return LengthAwarePaginator<int, Company> */
    public function paginate(int $perPage): LengthAwarePaginator;

    public function findBySlug(string $slug): ?Company;

    public function findById(int $id): ?Company;

    public function create(array $attributes): Company;

    /** @param array<string, mixed> $attributes */
    public function update(Company $company, array $attributes): Company;
}
