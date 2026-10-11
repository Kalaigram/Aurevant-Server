<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

final class SupplierEloquentRepository implements SupplierRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return Supplier::query()
            ->where('company_id', $company->id)
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
