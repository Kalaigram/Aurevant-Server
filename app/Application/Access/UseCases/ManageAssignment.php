<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Application\Audit\Audit;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ManageAssignment
{
    public function __construct(
        private AssignmentRepositoryInterface $assignments,
        private BranchRepositoryInterface $branches,
        private RoleRepositoryInterface $roles,
    ) {}

    public function create(
        Company $company,
        User $user,
        ?int $branchId,
        ?int $roleId,
        string $startedAt,
    ): AssignmentDTO {
        if ($branchId !== null) {
            $this->branches->findForCompany($company, $branchId) ?? abort(404);
        }

        if ($roleId !== null && $this->roles->findForCompany($company, $roleId) === null) {
            abort(404);
        }

        return AssignmentDTO::fromModel(
            $this->assignments->create($company, [
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'role_id' => $roleId,
                'started_at' => $startedAt,
            ])
        );
    }

    public function close(Company $company, int $id, string $endedAt): AssignmentDTO
    {
        $assignment = $this->assignments->findForCompany($company, $id) ?? abort(404);
        $role = $assignment->role;
        $isActive = $assignment->ended_at === null;

        if (
            $isActive
            && $role !== null
            && $role->name === 'Pemilik'
            && ! $this->assignments->hasOtherActiveWithRole($company, $assignment->id, $role->id)
        ) {
            throw ValidationException::withMessages([
                'assignment' => 'Penempatan Pemilik terakhir tidak bisa ditutup.',
            ]);
        }

        $assignment->ended_at = $endedAt;
        $saved = $this->assignments->save($assignment);

        if ($isActive && $role !== null) {
            setPermissionsTeamId($company->id);

            if (! $this->assignments->hasOtherActiveWithRole($company, $saved->id, $role->id)) {
                $saved->user?->removeRole($role);
            }
        }

        if ($isActive && $saved->user !== null && ! $this->assignments->hasAnyActiveForUser($saved->user)) {
            $saved->user->tokens()->delete();
        }

        Audit::record(
            $company,
            $saved->branch_id,
            'access.assignment.close',
            'assignment',
            $saved->id,
            ['endedAt' => null, 'role' => $role?->name],
            ['endedAt' => $saved->ended_at?->toDateString(), 'role' => $role?->name],
        );

        return AssignmentDTO::fromModel($saved);
    }
}
