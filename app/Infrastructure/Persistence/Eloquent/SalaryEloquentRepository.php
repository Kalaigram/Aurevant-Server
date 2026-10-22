<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Payroll\Repositories\SalaryRepositoryInterface;
use App\Models\Company;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class SalaryEloquentRepository implements SalaryRepositoryInterface
{
    public function paginateComponents(
        Company $company,
        int $perPage,
        ?string $q = null,
        ?bool $active = null,
    ): LengthAwarePaginator {
        $term = $q !== null && trim($q) !== '' ? '%'.trim($q).'%' : null;

        return SalaryComponent::query()
            ->where('company_id', $company->id)
            ->when($term !== null, fn ($query) => $query->where('name', 'like', $term))
            ->when($active !== null, fn ($query) => $query->where('active', $active))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createComponent(Company $company, array $attributes): SalaryComponent
    {
        return SalaryComponent::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh();
    }

    public function updateComponent(SalaryComponent $component, array $attributes): SalaryComponent
    {
        $component->update($attributes);

        return $component->refresh();
    }

    public function deleteComponent(SalaryComponent $component): void
    {
        $component->delete();
    }

    public function findComponent(Company $company, int $id): ?SalaryComponent
    {
        return SalaryComponent::query()
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function assignComponent(Company $company, User $user, int $componentId, float $amount): void
    {
        $component = $this->findComponent($company, $componentId) ?? abort(404);

        $user->employeeSalaries()->updateOrCreate(
            ['salary_component_id' => $component->id],
            ['company_id' => $company->id, 'amount' => $amount]
        );
    }

    public function componentsForUser(Company $company, User $user): array
    {
        return $user->employeeSalaries()
            ->with('component')
            ->where('company_id', $company->id)
            ->whereHas('component', fn ($query) => $query->where('active', true))
            ->get()
            ->map(fn ($row) => [
                'id' => $row->component->id,
                'name' => $row->component->name,
                'calc_type' => $row->component->calc_type,
                'deductible' => (bool) $row->component->deductible,
                'taxable' => (bool) $row->component->taxable,
                'bpjs_base' => (bool) $row->component->bpjs_base,
                'amount' => (float) $row->amount,
            ])
            ->all();
    }
}
