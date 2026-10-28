<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Repositories\ServicePackageRepositoryInterface;
use App\Models\Company;
use App\Models\ServicePackage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ServicePackageEloquentRepository implements ServicePackageRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator
    {
        return $company->servicePackages()
            ->with(['items.item'])
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($item) => $item
                    ->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?ServicePackage
    {
        return $company->servicePackages()
            ->with(['items.item'])
            ->whereKey($id)
            ->first();
    }

    public function createForCompany(Company $company, array $attributes, array $items): ServicePackage
    {
        return DB::transaction(function () use ($company, $attributes, $items) {
            $package = $company->servicePackages()->create($attributes);
            $package->items()->createMany($items);

            return $package->refresh()->load(['items.item']);
        });
    }

    public function update(ServicePackage $package, array $attributes, array $items): ServicePackage
    {
        return DB::transaction(function () use ($package, $attributes, $items) {
            $package->update($attributes);
            $package->items()->delete();
            $package->items()->createMany($items);

            return $package->refresh()->load(['items.item']);
        });
    }

    public function delete(ServicePackage $package): void
    {
        $package->delete();
    }
}
