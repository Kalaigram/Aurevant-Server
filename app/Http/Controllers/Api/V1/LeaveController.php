<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Attendance\UseCases\CorrectAttendance;
use App\Application\Employee\UseCases\EmployeeCard;
use App\Application\Leave\UseCases\DecideLeave;
use App\Application\Leave\UseCases\ListLeaves;
use App\Application\Leave\UseCases\RequestLeave;
use App\Domain\Attendance\Services\AttendanceStatuses;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LeaveController extends Controller
{
    public function index(Request $request, Company $company, ListLeaves $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'user_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', Rule::in(['diajukan', 'disetujui', 'ditolak'])],
        ]);

        $actor = $request->user();
        $canSeeAll = $actor->is_super_admin
            || $actor->can('absensi.approve')
            || $actor->can('karyawan.kelola');
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;

        if (! $canSeeAll) {
            if ($userId !== null && $userId !== $actor->id) {
                abort(403, 'Akses ditolak.');
            }

            $userId = $actor->id;
        }

        return ApiResponse::paginated(
            $useCase(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $userId,
                $validated['status'] ?? null,
            ),
            'Daftar pengajuan.'
        );
    }

    public function store(Request $request, Company $company, User $user, RequestLeave $useCase): JsonResponse
    {
        if ($request->user()->id !== $user->id && ! $request->user()->can('absensi.approve')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(AttendanceStatuses::all()))],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            $useCase($company, $user, $validated['type'], $validated['from_date'], $validated['to_date'], $validated['reason']),
            'Pengajuan dibuat.',
            201
        );
    }

    public function decide(Request $request, Company $company, int $leave, LeaveRepositoryInterface $leaves, DecideLeave $useCase): JsonResponse
    {
        $validated = $request->validate([
            'approve' => ['required', 'boolean'],
        ]);

        $found = $leaves->findForCompany($company, $leave) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $found, $request->user(), (bool) $validated['approve']),
            (bool) $validated['approve'] ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.'
        );
    }

    public function correct(Request $request, Company $company, int $attendance, CorrectAttendance $useCase): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(AttendanceStatuses::all()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            $useCase($company, $attendance, $validated['status'], $validated['note'] ?? null),
            'Absensi dikoreksi.'
        );
    }

    public function card(Request $request, Company $company, User $user, EmployeeCard $useCase): JsonResponse
    {
        $this->guardCard($request, $user);

        return ApiResponse::success($useCase($company, $user), 'Kartu karyawan.');
    }

    public function cardQr(Request $request, Company $company, User $user, EmployeeCard $useCase)
    {
        $this->guardCard($request, $user);

        $card = $useCase($company, $user);

        return response(
            QrCode::size(300)->generate($card['qrContent']),
            200,
            ['Content-Type' => 'image/svg+xml']
        );
    }

    private function guardCard(Request $request, User $user): void
    {
        $actor = $request->user();

        if ($actor->id !== $user->id && ! $actor->is_super_admin && ! $actor->can('karyawan.kelola')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
