<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Models\Company;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface SalaryRepositoryInterface
{
    /** @return LengthAwarePaginator<int, SalaryComponent> */
    public function paginateComponents(Company $company, int $perPage): LengthAwarePaginator;

    public function createComponent(Company $company, array $attributes): SalaryComponent;

    public function updateComponent(SalaryComponent $component, array $attributes): SalaryComponent;

    public function deleteComponent(SalaryComponent $component): void;

    public function findComponent(Company $company, int $id): ?SalaryComponent;

    public function assignComponent(Company $company, User $user, int $componentId, float $amount): void;

    /** @return list<array{id: int, name: string, calc_type: string, deductible: bool, amount: float}> */
    public function componentsForUser(Company $company, User $user): array;
}
