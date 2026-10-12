<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Application\Sale\DTOs\ShiftDTO;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Models\Branch;
use Illuminate\Validation\ValidationException;

final class OpenShift
{
    public function __construct(
        private CashShiftRepositoryInterface $shifts,
    ) {}

    public function __invoke(Branch $branch, int $userId, string $openingCash): ShiftDTO
    {
        if ($this->shifts->findOpenForBranch($branch) !== null) {
            throw ValidationException::withMessages([
                'shift' => 'Masih ada shift terbuka di cabang ini.',
            ]);
        }

        return ShiftDTO::fromModel(
            $this->shifts->openShift($branch, $userId, $openingCash)
        );
    }
}
