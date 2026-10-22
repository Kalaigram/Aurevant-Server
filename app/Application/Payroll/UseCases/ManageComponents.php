<?php

declare(strict_types=1);

namespace App\Application\Payroll\UseCases;

use App\Application\Payroll\DTOs\SalaryComponentDTO;
use App\Domain\Payroll\Repositories\SalaryRepositoryInterface;
use App\Models\Company;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ManageComponents
{
    public function __construct(
        private SalaryRepositoryInterface $salaries,
    ) {}

    /** @return LengthAwarePaginator<int, SalaryComponentDTO> */
    public function list(
        Company $company,
        int $perPage,
        ?string $q = null,
        ?bool $active = null,
    ): LengthAwarePaginator {
        return $this->salaries->paginateComponents($company, $perPage, $q, $active)
            ->through(fn ($component) => SalaryComponentDTO::fromModel($component));
    }

    public function create(Company $company, array $attributes): SalaryComponentDTO
    {
        return SalaryComponentDTO::fromModel($this->salaries->createComponent($company, $attributes));
    }

    public function assign(Company $company, User $user, int $componentId, float $amount): void
    {
        $this->salaries->assignComponent($company, $user, $componentId, $amount);
    }
}
