<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Hr\UseCases\ManageLeaveBalances;
use App\Application\Hr\UseCases\ManageOvertime;
use App\Application\Hr\UseCases\ManageShiftSwaps;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    public function leaveBalances(Request $request, Company $company, ManageLeaveBalances $useCase): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'user_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::success(
            $useCase->list(
                $company,
                $request->user(),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                (int) ($validated['year'] ?? now()->year),
            ),
            'Saldo cuti.'
        );
    }

    public function upsertLeaveBalance(Request $request, Company $company, User $user, ManageLeaveBalances $useCase): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'entitlement' => ['required', 'numeric', 'min:0', 'max:365'],
            'carryover' => ['sometimes', 'numeric', 'min:0', 'max:365'],
        ]);

        return ApiResponse::success(
            $useCase->upsert(
                $company,
                $user,
                (int) $validated['year'],
                (float) $validated['entitlement'],
                (float) ($validated['carryover'] ?? 0),
            ),
            'Saldo cuti disimpan.'
        );
    }

    public function overtimeIndex(Request $request, Company $company, ManageOvertime $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['diajukan', 'disetujui', 'ditolak'])],
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                $company,
                $request->user(),
                (int) ($validated['per_page'] ?? 15),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                $validated['status'] ?? null,
                $validated['month'] ?? null,
            ),
            'Pengajuan lembur.'
        );
    }

    public function overtimeStore(Request $request, Company $company, ManageOvertime $useCase): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $target = isset($validated['user_id'])
            ? (User::query()->whereKey((int) $validated['user_id'])->first() ?? abort(404))
            : $request->user();

        return ApiResponse::success(
            $useCase->create(
                $company,
                $request->user(),
                $target,
                $validated['date'],
                $validated['start_time'],
                $validated['end_time'],
                $validated['reason'],
            ),
            'Pengajuan lembur dibuat.',
            201
        );
    }

    public function overtimeDecide(Request $request, Company $company, int $overtime, ManageOvertime $useCase): JsonResponse
    {
        $validated = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $useCase->find($company, $overtime) ?? abort(404);

        return ApiResponse::success(
            $useCase->decide(
                $company,
                $found,
                $request->user(),
                (bool) $validated['approve'],
                $validated['note'] ?? null,
            ),
            (bool) $validated['approve'] ? 'Lembur disetujui.' : 'Lembur ditolak.'
        );
    }

    public function shiftSwapIndex(Request $request, Company $company, ManageShiftSwaps $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['diajukan', 'disetujui', 'ditolak'])],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                $company,
                $request->user(),
                (int) ($validated['per_page'] ?? 15),
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                $validated['status'] ?? null,
            ),
            'Pengajuan tukar shift.'
        );
    }

    public function shiftSwapStore(Request $request, Company $company, ManageShiftSwaps $useCase): JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'requester_shift_id' => ['sometimes', 'nullable', 'integer'],
            'target_shift_id' => ['sometimes', 'nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $target = User::query()->whereKey((int) $validated['target_user_id'])->first() ?? abort(404);

        return ApiResponse::success(
            $useCase->create($company, $request->user(), $target, $validated['date'], $validated),
            'Pengajuan tukar shift dibuat.',
            201
        );
    }

    public function shiftSwapDecide(Request $request, Company $company, int $swap, ManageShiftSwaps $useCase): JsonResponse
    {
        $validated = $request->validate([
            'approve' => ['required', 'boolean'],
        ]);

        $found = $useCase->find($company, $swap) ?? abort(404);

        return ApiResponse::success(
            $useCase->decide($company, $found, $request->user(), (bool) $validated['approve']),
            (bool) $validated['approve'] ? 'Tukar shift disetujui.' : 'Tukar shift ditolak.'
        );
    }
}
