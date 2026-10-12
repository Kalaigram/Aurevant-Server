<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\DTOs\RoleDTO;
use App\Application\Access\UseCases\AssignRole;
use App\Application\Access\UseCases\CreateRole;
use App\Application\Access\UseCases\DeleteRole;
use App\Application\Access\UseCases\ListRoles;
use App\Application\Access\UseCases\UpdateRole;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AccessRoleController extends Controller
{
    public function permissions(): JsonResponse
    {
        return ApiResponse::success(PermissionCatalog::all(), 'Katalog permission.');
    }

    public function templates(): JsonResponse
    {
        return ApiResponse::success(array_keys(RoleTemplates::all()), 'Template role.');
    }

    public function index(Request $request, Company $company, ListRoles $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar role.'
        );
    }

    public function store(Request $request, Company $company, CreateRole $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('company_id', $company->id),
            ],
            'template' => ['nullable', 'string', Rule::in(array_keys(RoleTemplates::all()))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ]);

        return ApiResponse::success(
            $useCase($company, $validated['name'], $validated['permissions'] ?? [], $validated['template'] ?? null),
            'Role dibuat.',
            201
        );
    }

    public function show(Company $company, int $role, RoleRepositoryInterface $roles): JsonResponse
    {
        return ApiResponse::success(
            RoleDTO::fromModel($this->scoped($company, $role, $roles)),
            'Detail role.'
        );
    }

    public function update(Request $request, Company $company, int $role, RoleRepositoryInterface $roles, UpdateRole $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $role, $roles);
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('company_id', $company->id)->ignore($scoped->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ]);

        return ApiResponse::success(
            $useCase($scoped, $validated['name'], $validated['permissions'] ?? null),
            'Role diperbarui.'
        );
    }

    public function destroy(Company $company, int $role, RoleRepositoryInterface $roles, DeleteRole $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $role, $roles));

        return ApiResponse::success(null, 'Role dihapus.');
    }

    public function assign(Request $request, Company $company, User $user, AssignRole $useCase): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $useCase($company, $user, $validated['role']);

        return ApiResponse::success(null, 'Role diberikan.');
    }

    public function revoke(Company $company, User $user, string $role, AssignRole $useCase): JsonResponse
    {
        $useCase->revoke($company, $user, $role);

        return ApiResponse::success(null, 'Role dicabut.');
    }

    private function scoped(Company $company, int $id, RoleRepositoryInterface $roles): Role
    {
        return $roles->findForCompany($company, $id) ?? abort(404);
    }
}
