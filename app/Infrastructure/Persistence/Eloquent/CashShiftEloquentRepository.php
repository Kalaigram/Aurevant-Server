<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Models\Branch;
use App\Models\CashShift;

final class CashShiftEloquentRepository implements CashShiftRepositoryInterface
{
    public function openShift(Branch $branch, int $userId, string $openingCash): CashShift
    {
        return CashShift::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'user_id' => $userId,
            'status' => 'terbuka',
            'opening_cash' => $openingCash,
            'opened_at' => now(),
        ])->refresh();
    }

    public function findOpenForBranch(Branch $branch): ?CashShift
    {
        return CashShift::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'terbuka')
            ->orderByDesc('id')
            ->first();
    }

    public function findForBranch(Branch $branch, int $id): ?CashShift
    {
        return CashShift::query()
            ->where('branch_id', $branch->id)
            ->whereKey($id)
            ->first();
    }

    public function save(CashShift $shift): CashShift
    {
        $shift->save();

        return $shift->refresh();
    }
}
