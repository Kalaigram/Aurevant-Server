<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Audit\Audit;
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
        $dto = $this->move($transfer, 'draft', 'diajukan');
        $this->trail($transfer, 'stock.transfer.submit', 'draft', 'diajukan');

        return $dto;
    }

    public function approve(StockTransfer $transfer): StockTransferDTO
    {
        $dto = $this->move($transfer, 'diajukan', 'disetujui');
        $this->trail($transfer, 'stock.transfer.approve', 'diajukan', 'disetujui');

        return $dto;
    }

    public function cancel(StockTransfer $transfer): StockTransferDTO
    {
        if (! in_array($transfer->status, ['draft', 'diajukan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Transfer status '.$transfer->status.' tidak bisa dibatalkan.',
            ]);
        }

        $from = $transfer->status;
        $transfer->status = 'dibatalkan';

        $dto = StockTransferDTO::fromModel($this->transfers->save($transfer));
        $this->trail($transfer, 'stock.transfer.cancel', $from, 'dibatalkan');

        return $dto;
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

    private function trail(StockTransfer $transfer, string $action, string $from, string $to): void
    {
        Audit::record(
            $transfer->company, $transfer->from_branch_id, $action, 'stock_transfer', $transfer->id,
            ['status' => $from],
            ['status' => $to],
        );
    }
}
