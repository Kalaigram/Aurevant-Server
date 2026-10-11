<?php

declare(strict_types=1);

namespace App\Application\Branch\UseCases;

use App\Application\Branch\DTOs\BranchDTO;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Company;

final class CreateBranch
{
    public function __construct(
        private BranchRepositoryInterface $branches,
    ) {}

    public function __invoke(Company $company, array $attributes): BranchDTO
    {
        return BranchDTO::fromModel(
            $this->branches->createForCompany($company, $attributes)
        );
    }
}
