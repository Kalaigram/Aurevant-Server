<?php

declare(strict_types=1);

namespace App\Application\Attendance\UseCases;

use App\Domain\Attendance\Services\QrTokenService;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Str;

final class BranchQr
{
    public function __construct(
        private BranchRepositoryInterface $branches,
        private QrTokenService $qr,
    ) {}

    /** @return array{token: string, expires_in: int} */
    public function __invoke(Company $company, Branch $branch): array
    {
        if ($branch->qr_secret === null) {
            $branch->qr_secret = Str::random(32);
            $branch->save();
        }

        return $this->qr->generate($branch->qr_secret, $branch->id);
    }
}
