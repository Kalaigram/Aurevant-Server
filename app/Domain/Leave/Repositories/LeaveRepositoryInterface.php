<?php

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface LeaveRepositoryInterface
{
    /** @return LengthAwarePaginator<int, LeaveRequest> */
    public function paginateForCompany(Company $company, int $perPage, ?int $userId = null, ?string $status = null): LengthAwarePaginator;

    public function findForCompany(Company $company, int $id): ?LeaveRequest;

    public function create(Company $company, User $user, array $attributes): LeaveRequest;

    public function save(LeaveRequest $leave): LeaveRequest;

    public function approvedCovering(User $user, string $date): ?LeaveRequest;
}
