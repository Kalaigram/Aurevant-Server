<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Payroll\DTOs\PayrollPeriodDTO;
use App\Application\Payroll\UseCases\CalculatePayroll;
use App\Application\Payroll\UseCases\ManageAdjustments;
use App\Application\Payroll\UseCases\ManageComponents;
use App\Application\Payroll\UseCases\Payslip;
use App\Application\Payroll\UseCases\TransitionPayroll;
use App\Domain\Payroll\Repositories\PayrollRepositoryInterface;
use App\Domain\Payroll\Repositories\SalaryRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function components(Request $request, Company $company, ManageComponents $useCase): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $useCase->list($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar komponen gaji.'
        );
    }

    public function storeComponent(Request $request, Company $company, ManageComponents $useCase): JsonResponse
    {
        return ApiResponse::success(
            $useCase->create($company, $request->validate($this->componentRules())),
            'Komponen dibuat.',
            201
        );
    }

    public function updateComponent(Request $request, Company $company, int $component, SalaryRepositoryInterface $salaries, ManageComponents $useCase): JsonResponse
    {
        $found = $salaries->findComponent($company, $component) ?? abort(404);

        return ApiResponse::success(
            $useCase->update($found, $request->validate($this->componentRules())),
            'Komponen diperbarui.'
        );
    }

    public function assignComponent(Request $request, Company $company, User $user, ManageComponents $useCase): JsonResponse
    {
        $validated = $request->validate([
            'component_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $useCase->assign($company, $user, (int) $validated['component_id'], (float) $validated['amount']);

        return ApiResponse::success(null, 'Komponen ditetapkan.');
    }

    public function periods(Request $request, Company $company, PayrollRepositoryInterface $payrolls): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $payrolls->paginatePeriods($company, (int) ($validated['per_page'] ?? 15)),
            'Daftar periode.'
        );
    }

    public function myPeriods(Request $request, Company $company, PayrollRepositoryInterface $payrolls): JsonResponse
    {
        abort_unless($request->user()->can('gaji.lihat_sendiri'), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::paginated(
            $payrolls->paginatePeriodsForUser(
                $company,
                $request->user(),
                (int) ($validated['per_page'] ?? 50),
            )->through(fn ($period) => PayrollPeriodDTO::fromModel($period)),
            'Daftar periode slip saya.'
        );
    }

    public function storePeriod(Request $request, Company $company, PayrollRepositoryInterface $payrolls): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'divisor' => ['sometimes', 'integer', 'min:1', 'max:31'],
        ]);

        $period = $payrolls->createPeriod($company, $validated);

        return ApiResponse::success($period, 'Periode dibuat.', 201);
    }

    public function showPeriod(Company $company, int $period, PayrollRepositoryInterface $payrolls): JsonResponse
    {
        $found = $payrolls->findPeriod($company, $period) ?? abort(404);
        $found->load('lines.user');

        $employees = $found->lines->groupBy('user_id')->map(
            fn ($lines, $userId) => [
                'user_id' => (int) $userId,
                'name' => $lines->first()->user->name,
                'net' => round($lines->sum(fn ($line) => (float) $line->amount), 2),
                'lines' => $lines->values(),
            ]
        )->values();

        return ApiResponse::success([
            'period' => $found,
            'employees' => $employees,
        ], 'Detail periode.');
    }

    public function calculate(Company $company, int $period, PayrollRepositoryInterface $payrolls, CalculatePayroll $useCase): JsonResponse
    {
        $found = $payrolls->findPeriod($company, $period) ?? abort(404);

        return ApiResponse::success(
            $useCase($company, $found),
            'Periode dihitung ulang.'
        );
    }

    public function adjust(Request $request, Company $company, int $period, User $user, PayrollRepositoryInterface $payrolls, ManageAdjustments $useCase): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
        ]);

        $found = $payrolls->findPeriod($company, $period) ?? abort(404);

        return ApiResponse::success(
            $useCase->add($found, $user, $validated['label'], (float) $validated['amount']),
            'Penyesuaian ditambah.',
            201
        );
    }

    public function review(Company $company, int $period, PayrollRepositoryInterface $payrolls, TransitionPayroll $flow): JsonResponse
    {
        $found = $payrolls->findPeriod($company, $period) ?? abort(404);

        return ApiResponse::success($flow->review($found), 'Periode direview.');
    }

    public function approve(Company $company, int $period, PayrollRepositoryInterface $payrolls, TransitionPayroll $flow): JsonResponse
    {
        $found = $payrolls->findPeriod($company, $period) ?? abort(404);

        return ApiResponse::success($flow->approve($found), 'Periode disetujui dan dikunci.');
    }

    public function pay(Company $company, int $period, PayrollRepositoryInterface $payrolls, TransitionPayroll $flow): JsonResponse
    {
        $found = $payrolls->findPeriod($company, $period) ?? abort(404);

        return ApiResponse::success($flow->pay($found), 'Periode dibayar.');
    }

    public function slip(Request $request, Company $company, int $period, User $user, Payslip $useCase): JsonResponse
    {
        $me = $request->user();
        $own = $me->id === $user->id && $me->can('gaji.lihat_sendiri');
        if (! $own && ! $me->can('gaji.kelola')) {
            abort(403, 'Akses ditolak.');
        }

        return ApiResponse::success($useCase($company, $period, $user), 'Slip gaji.');
    }

    /** @return array<string, mixed> */
    private function componentRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'calc_type' => ['required', 'string', Rule::in(['monthly_fixed', 'daily_rate', 'commission', 'manual'])],
            'deductible' => ['sometimes', 'boolean'],
            'taxable' => ['sometimes', 'boolean'],
            'bpjs_base' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
