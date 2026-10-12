<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Audit\UseCases\ListAuditLogs;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request, Company $company, ListAuditLogs $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'action' => ['sometimes', 'string', 'max:100'],
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'user_id' => ['sometimes', 'integer'],
        ]);

        return ApiResponse::paginated(
            $useCase(
                $company,
                (int) ($validated['per_page'] ?? 15),
                $validated['action'] ?? null,
                $validated['entity_type'] ?? null,
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            ),
            'Jejak audit.'
        );
    }
}
