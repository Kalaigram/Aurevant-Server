<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Approval\DTOs\ApprovalDTO;
use App\Application\Approval\UseCases\DecideApproval;
use App\Application\Approval\UseCases\RequestApproval;
use App\Domain\Approval\Services\ApprovalActions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    public function index(Request $request, Company $company, RequestApproval $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['diajukan', 'disetujui', 'ditolak'])],
            'action' => ['sometimes', 'string', Rule::in(array_keys(ApprovalActions::all()))],
        ]);

        return ApiResponse::paginated(
            $useCase->list(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $validated['status'] ?? null,
                $validated['action'] ?? null,
            ),
            'Pengajuan persetujuan.'
        );
    }

    public function store(Request $request, Company $company, RequestApproval $useCase): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(array_keys(ApprovalActions::all()))],
            'entity_id' => ['nullable', 'integer'],
            'payload' => ['sometimes', 'array'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            $useCase->request(
                $company,
                $request->user(),
                $validated['action'],
                isset($validated['entity_id']) ? (int) $validated['entity_id'] : null,
                $validated['payload'] ?? [],
                $validated['note'] ?? null,
            ),
            'Pengajuan dibuat.',
            201
        );
    }

    public function show(Company $company, int $approval, RequestApproval $useCase): JsonResponse
    {
        $found = $useCase->find($company, $approval) ?? abort(404);

        return ApiResponse::success(
            ApprovalDTO::fromModel($found, ApprovalActions::find($found->action)),
            'Detail pengajuan.'
        );
    }

    public function approve(Request $request, Company $company, int $approval, RequestApproval $finder, DecideApproval $useCase): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $finder->find($company, $approval) ?? abort(404);

        return ApiResponse::success(
            $useCase->decide($company, $found, $request->user(), true, $validated['note'] ?? null),
            'Pengajuan disetujui dan dijalankan.'
        );
    }

    public function reject(Request $request, Company $company, int $approval, RequestApproval $finder, DecideApproval $useCase): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $found = $finder->find($company, $approval) ?? abort(404);

        return ApiResponse::success(
            $useCase->decide($company, $found, $request->user(), false, $validated['note'] ?? null),
            'Pengajuan ditolak.'
        );
    }
}
