<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

final class BranchEloquentRepository implements BranchRepositoryInterface
{
    public function paginateForCompany(
        Company $company,
        int $perPage,
        ?string $q = null,
        ?string $type = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $term = $q !== null && trim($q) !== '' ? '%'.trim($q).'%' : null;

        return $company->branches()
            ->when($term !== null, function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->when($type !== null && $type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Branch
    {
        return $company->branches()->whereKey($id)->first();
    }

    public function findByCodeForCompany(Company $company, string $code): ?Branch
    {
        return $company->branches()->where('code', $code)->first();
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
