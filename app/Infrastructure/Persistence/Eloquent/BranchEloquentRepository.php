<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class BranchEloquentRepository implements BranchRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return $company->branches()->orderBy('code')->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Branch
    {
        return $company->branches()->whereKey($id)->first();
    }

    public function createForCompany(Company $company, array $attributes): Branch
    {
        $branch = $company->branches()->create($attributes);

        return $branch->refresh();
    }

    public function update(Branch $branch, array $attributes): Branch
    {
        $branch->update($attributes);

        return $branch->refresh();
    }

    public function delete(Branch $branch): void
    {
        $branch->delete();
    }
}
