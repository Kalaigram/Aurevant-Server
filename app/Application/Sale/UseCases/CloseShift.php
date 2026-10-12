<?php

declare(strict_types=1);

namespace App\Application\Sale\UseCases;

use App\Application\Sale\DTOs\ShiftDTO;
use App\Domain\Sale\Repositories\CashShiftRepositoryInterface;
use App\Models\CashShift;
use Illuminate\Validation\ValidationException;

final class CloseShift
{
    public function __construct(
        private CashShiftRepositoryInterface $shifts,
    ) {}

    public function __invoke(CashShift $shift, string $countedCash): ShiftDTO
    {
        if ($shift->status !== 'terbuka') {
            throw ValidationException::withMessages([
                'shift' => 'Shift sudah ditutup.',
            ]);
        }

        $tunaiMasuk = (float) $shift->sales()
            ->withSum(['payments as tunai_sum' => fn ($query) => $query->where('method', 'tunai')], 'amount')
            ->get()
            ->sum('tunai_sum');

        $system = (float) $shift->opening_cash + $tunaiMasuk;
        $counted = (float) $countedCash;

        $shift->closing_cash_counted = $counted;
        $shift->closing_cash_system = $system;
        $shift->difference = $counted - $system;
        $shift->status = 'ditutup';
        $shift->closed_at = now();

        return ShiftDTO::fromModel($this->shifts->save($shift));
    }
}
