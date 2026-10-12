<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Sale\Repositories\SaleSequenceRepositoryInterface;
use App\Models\Branch;
use App\Models\SaleSequence;
use Illuminate\Support\Facades\DB;

final class SaleSequenceEloquentRepository implements SaleSequenceRepositoryInterface
{
    public function nextNumber(Branch $branch, string $date): int
    {
        return DB::transaction(function () use ($branch, $date) {
            $sequence = SaleSequence::query()
                ->where('branch_id', $branch->id)
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = SaleSequence::query()->create([
                    'branch_id' => $branch->id,
                    'date' => $date,
                    'last_number' => 0,
                ]);
            }

            $sequence->last_number += 1;
            $sequence->save();

            return $sequence->last_number;
        });
    }
}
