<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Employee\UseCases\CreateEmployee;
use App\Application\Employee\UseCases\ResetEmployeePassword;
use App\Domain\Payroll\Services\PtkpTable;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function store(Request $request, Company $company, CreateEmployee $useCase): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['nullable', 'string', 'min:8'],
            'branch_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
            'ptkp_status' => ['nullable', 'string', Rule::in(PtkpTable::statuses())],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $validated['name'],
                $validated['email'],
                $validated['phone'] ?? null,
                $validated['password'] ?? null,
                isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
                isset($validated['role_id']) ? (int) $validated['role_id'] : null,
                $validated['ptkp_status'] ?? null,
            ),
            'Karyawan dibuat. Password awal hanya ditampilkan sekali.',
            201
        );
    }

    public function resetPassword(Request $request, Company $company, User $user, ResetEmployeePassword $useCase): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        return ApiResponse::success(
            ['initialPassword' => $useCase($company, $user, $validated['password'] ?? null)],
            'Password karyawan direset. Password baru hanya ditampilkan sekali.'
        );
    }
}
