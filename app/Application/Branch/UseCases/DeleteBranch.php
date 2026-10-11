<?php

declare(strict_types=1);

namespace App\Application\Branch\UseCases;

use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Branch;

final class DeleteBranch
{
    public function __construct(
        private BranchRepositoryInterface $branches,
    ) {}

    public function __invoke(Branch $branch): void
    {
        $this->branches->delete($branch);
    }
}
