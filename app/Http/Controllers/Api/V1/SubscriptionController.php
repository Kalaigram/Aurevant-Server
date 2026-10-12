<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\UseCases\ChangeSubscription;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function show(Company $company, ChangeSubscription $useCase): JsonResponse
    {
        return ApiResponse::success($useCase->show($company), 'Langganan perusahaan.');
    }

    public function update(Request $request, Company $company, ChangeSubscription $useCase): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['trial', 'aktif', 'tenggang', 'read_only', 'ditangguhkan'])],
            'plan_id' => ['nullable', 'integer'],
            'ends_at' => ['nullable', 'date'],
        ]);

        return ApiResponse::success(
            $useCase->change(
                $company,
                $validated['status'],
                isset($validated['plan_id']) ? (int) $validated['plan_id'] : null,
                $validated['ends_at'] ?? null,
            ),
            'Langganan diperbarui.'
        );
    }
}
