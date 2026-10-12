<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Service\DTOs\CustomerDTO;
use App\Application\Service\UseCases\CreateCustomer;
use App\Application\Service\UseCases\DeleteCustomer;
use App\Application\Service\UseCases\ListCustomers;
use App\Application\Service\UseCases\UpdateCustomer;
use App\Domain\Service\Repositories\CustomerRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request, Company $company, ListCustomers $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar pelanggan.'
        );
    }

    public function store(Request $request, Company $company, CreateCustomer $useCase): JsonResponse
    {
        $validated = $request->validate($this->rules($company));

        return ApiResponse::success(
            $useCase($company, $validated),
            'Pelanggan dibuat.',
            201
        );
    }

    public function show(Company $company, int $customer, CustomerRepositoryInterface $customers): JsonResponse
    {
        return ApiResponse::success(
            CustomerDTO::fromModel($this->scoped($company, $customer, $customers)),
            'Detail pelanggan.'
        );
    }

    public function update(Request $request, Company $company, int $customer, CustomerRepositoryInterface $customers, UpdateCustomer $useCase): JsonResponse
    {
        $scoped = $this->scoped($company, $customer, $customers);
        $validated = $request->validate($this->rules($company, $scoped->id));

        return ApiResponse::success(
            $useCase($scoped, $validated),
            'Pelanggan diperbarui.'
        );
    }

    public function destroy(Company $company, int $customer, CustomerRepositoryInterface $customers, DeleteCustomer $useCase): JsonResponse
    {
        $useCase($this->scoped($company, $customer, $customers));

        return ApiResponse::success(null, 'Pelanggan dihapus.');
    }

    private function scoped(Company $company, int $id, CustomerRepositoryInterface $customers): Customer
    {
        return $customers->findForCompany($company, $id) ?? abort(404);
    }

    /** @return array<string, mixed> */
    private function rules(Company $company, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('customers', 'phone')->where('company_id', $company->id)->ignore($ignoreId),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
