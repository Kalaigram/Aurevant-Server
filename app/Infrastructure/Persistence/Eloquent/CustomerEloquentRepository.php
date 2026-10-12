<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

final class CustomerEloquentRepository implements CustomerRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage): LengthAwarePaginator
    {
        return Customer::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?Customer
    {
        return Customer::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function createForCompany(Company $company, array $attributes): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->update($attributes);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
