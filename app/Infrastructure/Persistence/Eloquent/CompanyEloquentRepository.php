<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class CompanyEloquentRepository implements CompanyRepositoryInterface
{
    public function paginateForUser(User $user, int $perPage): LengthAwarePaginator
    {
        return Company::query()
            ->when(! $user->is_super_admin, function ($query) use ($user): void {
                $query->whereHas('assignments', function ($assignment) use ($user): void {
                    $assignment->where('user_id', $user->id)->whereNull('ended_at');
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Company
    {
        return Company::query()->where('slug', $slug)->first();
    }

    public function findById(int $id): ?Company
    {
        return Company::query()->find($id);
    }

    public function create(array $attributes): Company
    {
        return Company::query()->create($attributes);
    }

    public function update(Company $company, array $attributes): Company
    {
        $company->update($attributes);

        return $company->refresh();
    }
}
