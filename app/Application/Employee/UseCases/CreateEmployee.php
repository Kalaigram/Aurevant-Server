<?php

declare(strict_types=1);

namespace App\Application\Employee\UseCases;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Application\Audit\Audit;
use App\Application\Auth\DTOs\UserDTO;
use App\Application\Employee\DTOs\CreatedEmployee;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateEmployee
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private BranchRepositoryInterface $branches,
        private AssignmentRepositoryInterface $assignments,
    ) {}

    public function __invoke(
        Company $company,
        string $name,
        string $email,
        ?string $phone,
        ?string $password,
        ?int $branchId,
        ?int $roleId,
        ?string $ptkpStatus,
    ): CreatedEmployee {
        if ($branchId !== null && $this->branches->findForCompany($company, $branchId) === null) {
            abort(404);
        }

        $role = null;
        if ($roleId !== null) {
            $role = $this->roles->findForCompany($company, $roleId) ?? abort(404);
        }

        $plainPassword = $password ?? Str::random(10);

        [$user, $assignment] = DB::transaction(function () use ($company, $name, $email, $phone, $plainPassword, $branchId, $roleId, $role, $ptkpStatus): array {
            $user = $this->users->create([
                'name' => $name,
                'email' => strtolower($email),
                'phone' => $phone,
                'password' => Hash::make($plainPassword),
                'ptkp_status' => $ptkpStatus ?? 'TK/0',
            ]);

            if ($role !== null) {
                setPermissionsTeamId($company->id);
                $user->assignRole($role);
            }

            $assignment = $this->assignments->create($company, [
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'role_id' => $roleId,
                'started_at' => now()->toDateString(),
            ]);

            return [$user, $assignment];
        });

        Audit::record($company, $branchId, 'karyawan.buat', 'user', $user->id, null, [
            'email' => $user->email,
            'role_id' => $roleId,
        ]);

        return new CreatedEmployee(
            user: UserDTO::fromModel($user),
            initialPassword: $plainPassword,
            assignment: AssignmentDTO::fromModel($assignment),
        );
    }
}
