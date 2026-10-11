<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Company\UseCases\ListCompanies;
use App\Http\Controllers\Controller;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function index(ListCompanies $useCase): JsonResponse
    {
        return ApiResponse::success($useCase(), 'Daftar perusahaan');
    }
}
