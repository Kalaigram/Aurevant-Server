<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use Illuminate\Pagination\LengthAwarePaginator;

final class AssignmentEloquentRepository implements AssignmentRepositoryInterface
{
    public function paginateForCompany(
        Company $company,
        int $perPage,
        ?int $userId = null,
        bool $activeOnly = false,
        ?string $q = null,
        ?int $branchId = null,
        ?int $roleId = null,
    ): LengthAwarePaginator {
        $term = $q !== null && trim($q) !== '' ? '%'.trim($q).'%' : null;

        return EmployeeAssignment::query()
            ->with(['user', 'branch', 'role'])
            ->where('company_id', $company->id)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($activeOnly, fn ($query) => $query->whereNull('ended_at'))
            ->when($term !== null, function ($query) use ($term): void {
                $query->whereHas('user', function ($user) use ($term): void {
                    $user->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when($roleId !== null, fn ($query) => $query->where('role_id', $roleId))
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?EmployeeAssignment
    {
        return EmployeeAssignment::query()
            ->with(['user', 'branch', 'role'])
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function create(Company $company, array $attributes): EmployeeAssignment
    {
        return EmployeeAssignment::query()->create([
            'company_id' => $company->id,
            ...$attributes,
        ])->refresh()->load(['user', 'branch', 'role']);
    }

    public function save(EmployeeAssignment $assignment): EmployeeAssignment
    {
        $assignment->save();

        return $assignment->refresh()->load(['user', 'branch', 'role']);
    }
}
