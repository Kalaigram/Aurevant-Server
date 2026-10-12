<?php

declare(strict_types=1);

namespace App\Application\Employee\UseCases;

use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

final class EmployeeCard
{
    public function __construct(
        private AssignmentRepositoryInterface $assignments,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(Company $company, User $user): array
    {
        if ($user->employee_uuid === null) {
            $user->employee_uuid = (string) Str::uuid();
            $user->save();
        }

        $assignment = $this->assignments->paginateForCompany($company, 1, $user->id, true)->first();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employeeUuid' => $user->employee_uuid,
            'company' => $company->name,
            'branch' => $assignment?->branch?->name,
            'role' => $assignment?->role?->name,
            'qrContent' => $user->employee_uuid,
        ];
    }
}
