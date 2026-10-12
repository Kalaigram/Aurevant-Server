<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCases;

use App\Application\Access\DTOs\RoleDTO;
use App\Application\Billing\DTOs\SubscriptionDTO;
use App\Application\Branch\DTOs\BranchDTO;
use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Domain\Billing\Repositories\SubscriptionRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

final class CreateCompany
{
    public function __construct(
        private BranchRepositoryInterface $branches,
        private RoleRepositoryInterface $roles,
        private AssignmentRepositoryInterface $assignments,
        private SubscriptionRepositoryInterface $billing,
    ) {}

    /** @return array{company: CompanyDTO, branch: BranchDTO, role: RoleDTO, subscription: SubscriptionDTO} */
    public function __invoke(
        User $creator,
        string $name,
        string $branchCode,
        string $branchName,
        string $timezone,
    ): array {
        return DB::transaction(function () use ($creator, $name, $branchCode, $branchName, $timezone) {
            $slug = Str::slug($name);
            $suffix = 2;
            while (Company::query()->where('slug', $slug)->exists()) {
                $slug = Str::slug($name).'-'.$suffix;
                $suffix++;
            }

            $company = Company::query()->create([
                'name' => $name, 'slug' => $slug, 'status' => 'aktif',
            ]);

            setPermissionsTeamId($company->id);
            foreach (PermissionCatalog::all() as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            $branch = $this->branches->createForCompany($company, [
                'code' => $branchCode,
                'name' => $branchName,
                'type' => 'pusat',
                'timezone' => $timezone,
                'status' => 'aktif',
            ]);

            $role = $this->roles->create($company, 'Pemilik');
            $role = $this->roles->syncPermissions(
                $role,
                RoleTemplates::permissionsFor('Pemilik', PermissionCatalog::all())
            );
            $creator->assignRole($role);

            $this->assignments->create($company, [
                'user_id' => $creator->id,
                'branch_id' => null,
                'role_id' => $role->id,
                'started_at' => now()->toDateString(),
            ]);

            $subscription = $this->billing->startTrial($company);

            return [
                'company' => CompanyDTO::fromModel($company),
                'branch' => BranchDTO::fromModel($branch),
                'role' => RoleDTO::fromModel($role),
                'subscription' => SubscriptionDTO::fromModel($subscription),
            ];
        });
    }
}
