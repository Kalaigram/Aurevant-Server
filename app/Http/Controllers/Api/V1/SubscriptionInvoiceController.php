<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Billing\DTOs\InvoiceDTO;
use App\Application\Billing\UseCases\ManageInvoices;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionInvoiceController extends Controller
{
    public function index(Request $request, Company $company, ManageInvoices $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->list($company, (int) ($validated['per_page'] ?? 15)),
            'Tagihan langganan.'
        );
    }

    public function store(Request $request, Company $company, ManageInvoices $useCase): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::success(
            $useCase->issue($company, $validated),
            'Tagihan diterbitkan.',
            201
        );
    }

    public function show(Company $company, int $invoice, InvoiceRepositoryInterface $invoices): JsonResponse
    {
        $found = $invoices->findForCompany($company, $invoice) ?? abort(404);

        return ApiResponse::success(InvoiceDTO::fromModel($found), 'Detail tagihan.');
    }

    public function pay(Company $company, int $invoice, InvoiceRepositoryInterface $invoices, ManageInvoices $useCase): JsonResponse
    {
        $found = $invoices->findForCompany($company, $invoice) ?? abort(404);

        return ApiResponse::success($useCase->pay($company, $found), 'Tagihan lunas, masa aktif +30 hari.');
    }
}
