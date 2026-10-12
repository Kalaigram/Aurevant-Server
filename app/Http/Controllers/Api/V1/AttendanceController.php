<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Attendance\UseCases\BranchQr;
use App\Application\Attendance\UseCases\ClockIn;
use App\Application\Attendance\UseCases\ClockOut;
use App\Application\Attendance\UseCases\ListAttendances;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function qr(Company $company, int $branch, BranchRepositoryInterface $branches, BranchQr $useCase): JsonResponse
    {
        $found = $branches->findForCompany($company, $branch) ?? abort(404);

        return ApiResponse::success($useCase($company, $found), 'QR cabang.');
    }

    public function clockIn(Request $request, Company $company, ClockIn $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'qr_token' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'device_id' => ['required', 'string', 'max:255'],
            'selfie' => ['required', 'image', 'max:2048'],
        ]);

        $selfiePath = $request->file('selfie')->store('absensi');

        return ApiResponse::success(
            $useCase(
                $company,
                $request->user(),
                (int) $validated['branch_id'],
                $validated['qr_token'],
                (float) $validated['lat'],
                (float) $validated['lng'],
                $validated['device_id'],
                $selfiePath,
            ),
            'Absen masuk tercatat.',
            201
        );
    }

    public function clockOut(Request $request, Company $company, ClockOut $useCase): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'qr_token' => ['required', 'string'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        return ApiResponse::success(
            $useCase(
                $company,
                $request->user(),
                (int) $validated['branch_id'],
                $validated['qr_token'],
                (float) $validated['lat'],
                (float) $validated['lng'],
                $validated['device_id'],
            ),
            'Absen pulang tercatat.'
        );
    }

    public function mine(Request $request, Company $company, ListAttendances $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return ApiResponse::paginated(
            $useCase->mine(
                $request->user(),
                (int) ($validated['per_page'] ?? 15),
                $validated['from'],
                $validated['to']
            ),
            'Riwayat absensi saya.'
        );
    }

    public function branchDay(Request $request, Company $company, int $branch, BranchRepositoryInterface $branches, ListAttendances $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'date' => ['sometimes', 'date'],
        ]);

        $found = $branches->findForCompany($company, $branch) ?? abort(404);

        return ApiResponse::paginated(
            $useCase->forBranch(
                $found,
                (int) ($validated['per_page'] ?? 15),
                $validated['date'] ?? now()->toDateString()
            ),
            'Absensi cabang.'
        );
    }

    public function resetDevice(Company $company, User $user, ListAttendances $useCase): JsonResponse
    {
        $useCase->resetDevice($user);

        return ApiResponse::success(null, 'Perangkat direset.');
    }
}
