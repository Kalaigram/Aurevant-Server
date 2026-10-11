<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockTransferDTO;
use App\Domain\Stock\Repositories\StockTransferRepositoryInterface;
use App\Models\StockTransfer;
use Illuminate\Validation\ValidationException;

final class TransitionTransfer
{
    public function __construct(
        private StockTransferRepositoryInterface $transfers,
    ) {}

    public function submit(StockTransfer $transfer): StockTransferDTO
    {
        return $this->move($transfer, 'draft', 'diajukan');
    }

    public function approve(StockTransfer $transfer): StockTransferDTO
    {
        return $this->move($transfer, 'diajukan', 'disetujui');
    }

    public function cancel(StockTransfer $transfer): StockTransferDTO
    {
        if (! in_array($transfer->status, ['draft', 'diajukan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Transfer status '.$transfer->status.' tidak bisa dibatalkan.',
            ]);
        }

        $transfer->status = 'dibatalkan';

        return StockTransferDTO::fromModel($this->transfers->save($transfer));
    }

    private function move(StockTransfer $transfer, string $from, string $to): StockTransferDTO
    {
        if ($transfer->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'Transfer harus berstatus '.$from.' (saat ini: '.$transfer->status.').',
            ]);
        }

        $transfer->status = $to;

        return StockTransferDTO::fromModel($this->transfers->save($transfer));
    }
}
