<?php

declare(strict_types=1);

namespace App\Application\Branch\UseCases;

use App\Application\Branch\DTOs\BranchDTO;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Branch;

final class UpdateBranch
{
    public function __construct(
        private BranchRepositoryInterface $branches,
    ) {}

    public function __invoke(Branch $branch, array $attributes): BranchDTO
    {
        return BranchDTO::fromModel(
            $this->branches->update($branch, $attributes)
        );
    }
}
