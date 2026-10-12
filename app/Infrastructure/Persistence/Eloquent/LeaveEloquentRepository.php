<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class LeaveEloquentRepository implements LeaveRepositoryInterface
{
    public function paginateForCompany(Company $company, int $perPage, ?int $userId = null, ?string $status = null): LengthAwarePaginator
    {
        return LeaveRequest::query()
            ->with('user')
            ->where('company_id', $company->id)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForCompany(Company $company, int $id): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->with('user')
            ->where('company_id', $company->id)
            ->whereKey($id)
            ->first();
    }

    public function create(Company $company, User $user, array $attributes): LeaveRequest
    {
        return LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            ...$attributes,
        ])->refresh();
    }

    public function save(LeaveRequest $leave): LeaveRequest
    {
        $leave->save();

        return $leave->refresh();
    }

    public function approvedCovering(User $user, string $date): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'disetujui')
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->first();
    }
}
