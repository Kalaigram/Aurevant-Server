<?php

declare(strict_types=1);

namespace App\Application\Leave\UseCases;

use App\Application\Leave\DTOs\LeaveDTO;
use App\Domain\Leave\Repositories\LeaveRepositoryInterface;
use App\Models\Company;
use App\Models\User;

final class RequestLeave
{
    public function __construct(
        private LeaveRepositoryInterface $leaves,
    ) {}

    public function __invoke(Company $company, User $user, string $type, string $from, string $to, string $reason): LeaveDTO
    {
        return LeaveDTO::fromModel(
            $this->leaves->create($company, $user, [
                'type' => $type,
                'from_date' => $from,
                'to_date' => $to,
                'reason' => $reason,
                'status' => 'diajukan',
            ])
        );
    }
}
