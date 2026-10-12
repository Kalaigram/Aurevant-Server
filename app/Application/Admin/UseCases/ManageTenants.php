<?php

declare(strict_types=1);

namespace App\Application\Admin\UseCases;

use App\Application\Admin\DTOs\TenantDTO;
use App\Application\Audit\Audit;
use App\Models\Company;
use App\Models\EmployeeAssignment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class ManageTenants
{
    public const STATUSES = ['aktif', 'ditangguhkan', 'diarsipkan'];

    /** @return LengthAwarePaginator<int, TenantDTO> */
    public function list(int $perPage, ?string $search, ?string $status): LengthAwarePaginator
    {
        return Company::query()
            ->with('subscription.plan')
            ->withCount('branches')
            ->when($search !== null, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn ($company) => TenantDTO::fromModel($this->withUsers($company)));
    }

    public function show(int $id): TenantDTO
    {
        return TenantDTO::fromModel($this->withUsers($this->find($id)));
    }

    public function setStatus(Company $company, string $status): TenantDTO
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Status harus salah satu: '.implode(', ', self::STATUSES).'.',
            ]);
        }

        $before = $company->status;
        $company->status = $status;
        $company->save();

        Audit::record(
            $company, null,
            $status === 'aktif' ? 'tenant.aktifkan' : 'tenant.tangguhkan',
            'company', $company->id,
            ['status' => $before],
            ['status' => $status],
        );

        return TenantDTO::fromModel($this->withUsers($company->load('subscription.plan')));
    }

    private function find(int $id): Company
    {
        return Company::query()->with('subscription.plan')->whereKey($id)->first() ?? abort(404);
    }

    private function withUsers(Company $company): Company
    {
        $company->users_count = EmployeeAssignment::query()
            ->where('company_id', $company->id)
            ->distinct('user_id')
            ->count('user_id');

        return $company;
    }
}
