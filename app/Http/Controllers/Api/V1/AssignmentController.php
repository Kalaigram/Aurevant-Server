<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\DTOs\AssignmentDTO;
use App\Application\Access\UseCases\ListAssignments;
use App\Application\Access\UseCases\ManageAssignment;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request, Company $company, ListAssignments $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'integer'],
            'active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'integer'],
            'role_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::paginated(
            $useCase(
                $company,
                (int) ($validated['per_page'] ?? 15),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                (bool) ($validated['active'] ?? false),
                $validated['q'] ?? null,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                isset($validated['role_id']) ? (int) $validated['role_id'] : null,
            ),
            'Daftar penempatan.'
        );
    }

    public function store(Request $request, Company $company, User $user, ManageAssignment $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
            'started_at' => ['required', 'date'],
        ]);

        return ApiResponse::success(
            $useCase->create(
                $company,
                $user,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                isset($validated['role_id']) ? (int) $validated['role_id'] : null,
                $validated['started_at'],
            ),
            'Penempatan dibuat.',
            201
        );
    }

    public function show(Company $company, int $assignment, AssignmentRepositoryInterface $assignments): JsonResponse
    {
        return ApiResponse::success(
            AssignmentDTO::fromModel($this->scoped($company, $assignment, $assignments)),
            'Detail penempatan.'
        );
    }

    public function close(Request $request, Company $company, int $assignment, AssignmentRepositoryInterface $assignments, ManageAssignment $useCase): JsonResponse
    {
        $validated = $request->validate([
            'ended_at' => ['required', 'date'],
        ]);

        return ApiResponse::success(
            $useCase->close($company, $assignment, $validated['ended_at']),
            'Penempatan ditutup.'
        );
    }

    private function scoped(Company $company, int $id, AssignmentRepositoryInterface $assignments): EmployeeAssignment
    {
        return $assignments->findForCompany($company, $id) ?? abort(404);
    }
}
