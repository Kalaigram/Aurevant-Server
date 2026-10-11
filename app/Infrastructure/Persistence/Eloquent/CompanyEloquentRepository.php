<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Collection;

final class CompanyEloquentRepository implements CompanyRepositoryInterface
{
    public function all(): Collection
    {
        return Company::query()->orderBy('name')->get();
    }

    public function findBySlug(string $slug): ?Company
    {
        return Company::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): Company
    {
        return Company::query()->create($attributes);
    }
}
