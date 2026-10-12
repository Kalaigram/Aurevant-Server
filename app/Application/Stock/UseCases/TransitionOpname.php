<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Domain\Approval\Services\ApprovalLedger;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\StockOpname;
use Illuminate\Validation\ValidationException;

final class TransitionOpname
{
    public function __construct(
        private StockOpnameRepositoryInterface $opnames,
    ) {}

    public function submit(StockOpname $opname): StockOpnameDTO
    {
        $dto = $this->move($opname, 'draft', 'diajukan');
        ApprovalLedger::open(
            $opname->company, $opname->branch_id, 'stock_opname', $opname->id,
            'opname.approve', auth()->user(),
        );

        return $dto;
    }

    public function reject(StockOpname $opname): StockOpnameDTO
    {
        $dto = $this->move($opname, 'diajukan', 'ditolak');
        ApprovalLedger::resolve(
            $opname->company, 'stock_opname', $opname->id, 'opname.approve', auth()->user(), false,
        );

        return $dto;
    }

    private function move(StockOpname $opname, string $from, string $to): StockOpnameDTO
    {
        if ($opname->status !== $from) {
            throw ValidationException::withMessages([
                'status' => 'Opname harus berstatus '.$from.' (saat ini: '.$opname->status.').',
            ]);
        }

        $opname->status = $to;

        return StockOpnameDTO::fromModel($this->opnames->save($opname));
    }
}
