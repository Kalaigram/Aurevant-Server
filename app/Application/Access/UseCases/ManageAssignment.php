<?php

declare(strict_types=1);

namespace App\Application\Access\UseCases;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;
use App\Models\User;

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
        $assignment->ended_at = $endedAt;

        return AssignmentDTO::fromModel($this->assignments->save($assignment));
    }
}
