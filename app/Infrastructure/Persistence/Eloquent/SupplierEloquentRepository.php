<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

final class SupplierEloquentRepository implements SupplierRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?string $q = null): LengthAwarePaginator
    {
        return Supplier::query()
            ->where('company_id', $company->id)
            ->when($q !== null && $q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($supplier) => $supplier
                    ->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Supplier
    {
        return Supplier::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createForCompany(Company $company, array $attributes): Supplier
    {
        return Supplier::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function update(Supplier $supplier, array $attributes): Supplier
    {
        $supplier->update($attributes);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
