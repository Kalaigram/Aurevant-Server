<?php

declare(strict_types=1);

namespace App\Application\Stock\UseCases;

use App\Application\Audit\Audit;
use App\Application\Stock\DTOs\StockOpnameDTO;
use App\Domain\Stock\Repositories\StockOpnameRepositoryInterface;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveOpname
{
    public function __construct(
        private StockOpnameRepositoryInterface $opnames,
        private RecordStockMovement $recordMovement,
    ) {}

    public function __invoke(StockOpname $opname): StockOpnameDTO
    {
        if ($opname->status !== 'diajukan') {
            throw ValidationException::withMessages([
                'status' => 'Opname harus berstatus diajukan (saat ini: '.$opname->status.').',
            ]);
        }

        return DB::transaction(function () use ($opname) {
            foreach ($opname->items as $line) {
                $diff = (float) $line->qty_counted - (float) $line->qty_system;

                if ($diff !== 0.0) {
                    ($this->recordMovement)(
                        $opname->company,
                        $opname->branch,
                        $line->item_id,
                        'opname',
                        $diff,
                        null,
                        'Opname #'.$opname->id,
                    );
                }
            }

            $opname->status = 'disetujui';

            $dto = StockOpnameDTO::fromModel($this->opnames->save($opname));
            Audit::record(
                $opname->company, $opname->branch_id, 'stock.opname.approve', 'stock_opname', $opname->id,
                ['status' => 'diajukan'],
                ['status' => 'disetujui'],
            );

            return $dto;
        });
    }
}
