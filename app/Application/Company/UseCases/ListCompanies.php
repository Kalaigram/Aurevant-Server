<?php

declare(strict_types=1);

namespace App\Application\Company\UseCases;

use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListCompanies
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {}

    /** @return list<CompanyDTO> */
    public function __invoke(): array
    {
        return $this->companies->all()
            ->map(fn ($company) => CompanyDTO::fromModel($company))
            ->all();
    }

    /** @return LengthAwarePaginator<int, CompanyDTO> */
    public function paginated(int $perPage): LengthAwarePaginator
    {
        return $this->companies->paginate($perPage)
            ->through(fn ($company) => CompanyDTO::fromModel($company));
    }
}
