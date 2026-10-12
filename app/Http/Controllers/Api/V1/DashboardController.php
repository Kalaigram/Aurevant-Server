<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Dashboard\CompanyDashboard;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, Company $company, CompanyDashboard $dashboard): JsonResponse
    {
        return ApiResponse::success(
            $dashboard($company, $request->user()),
            'Dashboard.'
        );
    }
}
