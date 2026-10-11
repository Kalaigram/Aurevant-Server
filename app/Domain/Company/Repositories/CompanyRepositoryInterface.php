<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Models\Company;
use Illuminate\Support\Collection;

interface CompanyRepositoryInterface
{
    /** @return Collection<int, Company> */
    public function all(): Collection;

    public function findBySlug(string $slug): ?Company;

    public function create(array $attributes): Company;
}
