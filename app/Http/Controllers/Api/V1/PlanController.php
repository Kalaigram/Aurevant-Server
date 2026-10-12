<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\DTOs\PlanDTO;
use App\Application\Billing\UseCases\ListPlans;
use App\Application\Billing\UseCases\ManagePlans;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request, ListPlans $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase((int) ($validated['per_page'] ?? 15)),
            'Daftar paket.'
        );
    }

    public function store(Request $request, ManagePlans $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase->create($request->validate($this->rules())),
            'Paket dibuat.',
            201
        );
    }

    public function show(Plan $plan): JsonResponse
    {
        return ApiResponse::success(PlanDTO::fromModel($plan), 'Detail paket.');
    }

    public function update(Request $request, Plan $plan, ManagePlans $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase->update($plan, $request->validate($this->rules($plan->id))),
            'Paket diperbarui.'
        );
    }

    public function destroy(Plan $plan, ManagePlans $useCase): JsonResponse
    {
        $useCase->delete($plan);

        return ApiResponse::success(null, 'Paket dihapus.');
    }

    /** @return array<string, mixed> */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('plans', 'name')->ignore($ignoreId)],
            'max_branches' => ['required', 'integer', 'min:1'],
            'max_employees' => ['required', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
